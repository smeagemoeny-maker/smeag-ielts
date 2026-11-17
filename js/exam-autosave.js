/*
 * IELTS 응시 자동 저장 · 이어서 보기 (Listening · Reading · Writing) — 정전·인터넷 끊김·브라우저 꺼짐 대비
 *   저장하는 것: 답안, 사용한 시간(시험 화면이 켜져 있던 시간), Listening 음원 재생 위치
 *   1) 이 PC: 답을 고치는 즉시 + 1초마다(시간·음원 위치) 브라우저(localStorage)에 저장 → 인터넷 없이도, 전원이 꺼져도 남음
 *   2) 서버: 고친 뒤 2초, 10초마다, 창을 닫을 때 서버(exam_drafts)에 저장 → 다른 PC 로 다시 로그인해도 이어서
 *   다시 열면 둘 중 최근 것을 되살림. 서버는 응시 화면을 열 때 만든 행만 고치므로 제출 뒤 늦게 온 저장은 무시됨.
 *   시간은 이 화면이 켜져 있는 동안만 흐름 → 정전으로 꺼져 있던 동안은 남은 시간이 줄지 않음
 * 사용: const examSave = ExamAutosave.init(form, { key, started, elapsed, pos, server: { answers, t } | null })
 *   examSave.elapsed()          사용한 시간(초)
 *   examSave.startClock(onTick) 시간 세기 시작 (onTick(사용한 시간) 1초마다)
 *   examSave.pos() / setPos(t)  Listening 음원 위치(초)
 *   examSave.resumed            이어서 보는 시험인지 (답안·시간·음원 위치 중 하나라도 저장돼 있었음)
 */
(function () {
  const SERVER_DELAY = 2000, INTERVAL = 10000;
  const MAX_STEP = 90;   // 한 번에 셀 수 있는 최대 초 (컴퓨터가 잠들었다 깨어난 시간은 거의 세지 않음)
  const store = {
    get(k) { try { return JSON.parse(localStorage.getItem(k) || 'null'); } catch (e) { return null; } },
    set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); return true; } catch (e) { return false; } },
    del(k) { try { localStorage.removeItem(k); } catch (e) {} },
    keys() { try { return Object.keys(localStorage); } catch (e) { return []; } },
  };
  const baseName = el => (el.getAttribute('name') || '').replace(/\[\]$/, '');
  const isAnswer = n => /^(q\d{1,2}|task[12])$/.test(n);

  function fields(form) {
    return [...form.querySelectorAll('input:not([type=hidden]),select,textarea')].filter(el => isAnswer(baseName(el)));
  }

  // 지금 화면의 답 → { q1: "abc", q5: "B", q21: ["A","C"], task1: "..." }
  function collect(form) {
    const out = {};
    fields(form).forEach(el => {
      const n = baseName(el);
      if (el.type === 'checkbox') { out[n] = out[n] || []; if (el.checked) out[n].push(el.value); }
      else if (el.type === 'radio') { if (el.checked) out[n] = el.value; else if (!(n in out)) out[n] = ''; }
      else out[n] = el.value;
    });
    return out;
  }

  // 저장된 답을 화면에 넣고, 문항 번호 바·단어 수 등이 따라 바뀌도록 이벤트를 보냄
  function apply(form, data) {
    if (!data || typeof data !== 'object') return 0;
    let changed = 0;
    fields(form).forEach(el => {
      const n = baseName(el);
      if (!(n in data)) return;
      const v = data[n];
      let diff = false;
      if (el.type === 'checkbox') { const on = Array.isArray(v) && v.includes(el.value); diff = el.checked !== on; el.checked = on; }
      else if (el.type === 'radio') { const on = el.value === v; diff = el.checked !== on; el.checked = on; }
      else if (typeof v === 'string' && el.value !== v) { el.value = v; diff = true; }
      if (diff) {
        changed++;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
    return changed;
  }

  function toFormData(data, extra) {
    const fd = new FormData();
    fd.append('_autosave', '1');
    Object.keys(extra).forEach(k => fd.append(k, extra[k]));
    Object.keys(data).forEach(k => {
      if (Array.isArray(data[k])) data[k].forEach(v => fd.append(k + '[]', v));
      else fd.append(k, data[k]);
    });
    return fd;
  }

  function init(form, o) {
    if (!form || !o || !o.key) return null;
    const key = o.key + ':' + o.started;          // 응시마다 다른 칸 (새로 시작하면 옛 답 안 씀)
    const pKey = key + ':p';                      // 사용한 시간 · 음원 위치
    const url = location.href;

    // 같은 학생·세트·영역의 지난 응시 임시 저장은 지움
    store.keys().forEach(k => { if (k.indexOf(o.key + ':') === 0 && k !== key && k !== pKey) store.del(k); });

    // ── 상태 표시 (남은 시간 아래) ──
    const status = document.createElement('div');
    status.className = 'as-status';
    status.style.cssText = 'font-size:11px;color:#666;margin-top:2px;white-space:nowrap';
    const host = document.querySelector('.lp-status') || form;
    host.appendChild(status);
    function show(text, warn) { status.textContent = text; status.style.color = warn ? '#b45309' : '#666'; }
    const hhmm = () => new Date().toTimeString().slice(0, 5);

    // ── 사용한 시간 · 음원 위치: 이 PC 와 서버 중 큰 값 (둘 다 앞으로만 감) ──
    const lp = store.get(pKey) || {};
    let elapsed = Math.max(+o.elapsed || 0, +lp.e || 0);
    let pos = Math.max(+o.pos || 0, +lp.p || 0);
    let pdirty = elapsed > (+o.elapsed || 0) || pos > (+o.pos || 0);   // 서버보다 앞선 값이 이 PC 에 있으면 곧 보냄

    // 제출할 때 사용한 시간도 함께 보냄 (서버가 시간 초과 판단에 씀)
    const hid = document.createElement('input');
    hid.type = 'hidden'; hid.name = '_elapsed'; hid.value = String(Math.floor(elapsed));
    form.appendChild(hid);

    let lastLocal = 0;
    function saveProgress(force) {
      const now = Date.now();
      if (!force && now - lastLocal < 1000) return;
      lastLocal = now;
      store.set(pKey, { e: elapsed, p: pos, t: now });
    }

    // ── 되살리기: 이 PC 와 서버 중 최근 것 ──
    const local = store.get(key);
    const server = o.server && o.server.answers && !Array.isArray(o.server.answers) ? o.server : null;
    let restored = 0;
    if (local && (!server || local.t >= server.t)) restored = apply(form, local.answers);
    else if (server) restored = apply(form, server.answers);
    let dirty = !!(local && !local.synced && (!server || local.t > server.t));   // 서버에 못 보낸 것이 남아 있으면 곧 보냄
    const resumed = !!(restored || elapsed >= 1 || pos >= 1);
    show(resumed ? '✓ Test resumed where you stopped' : '✓ Autosave on');

    // ── 서버 저장 ──
    let timer = null, sending = false;
    function saveLocal(synced) {
      return store.set(key, { answers: collect(form), t: Date.now(), synced: !!synced });
    }
    function payload() { return toFormData(collect(form), { _elapsed: Math.floor(elapsed), _pos: pos.toFixed(1) }); }
    async function saveServer() {
      clearTimeout(timer); timer = null;
      if (sending || !(dirty || pdirty)) return;
      if (navigator.onLine === false) { show('Saved on this computer (offline)', true); return; }
      sending = true;
      const wasDirty = dirty;
      dirty = false; pdirty = false;
      if (wasDirty) show('Saving…');
      try {
        const res = await fetch(url, { method: 'POST', body: payload(), credentials: 'same-origin', cache: 'no-store' });
        const j = res.ok ? await res.json().catch(() => null) : null;
        if (j && j.ok) {
          const rec = store.get(key); if (rec) { rec.synced = true; store.set(key, rec); }
          show('✓ All answers saved ' + hhmm());
        } else {
          dirty = dirty || wasDirty; pdirty = true;
          show('Saved on this computer', true);
        }
      } catch (e) {
        dirty = dirty || wasDirty; pdirty = true;
        show('Saved on this computer (offline)', true);
      } finally {
        sending = false;
      }
    }
    function changed() {
      dirty = true;
      saveLocal(false);
      if (!timer) timer = setTimeout(saveServer, SERVER_DELAY);
    }

    form.addEventListener('input', e => { if (isAnswer(baseName(e.target))) changed(); });
    form.addEventListener('change', e => { if (isAnswer(baseName(e.target))) changed(); });
    setInterval(() => { if (dirty || pdirty) saveServer(); }, INTERVAL);
    window.addEventListener('online', () => { if (dirty || pdirty) saveServer(); });

    // 창을 닫거나 다른 화면으로 갈 때 마지막으로 한 번 더 (서버로는 sendBeacon)
    function flush() {
      saveLocal(!dirty);
      saveProgress(true);
      if ((dirty || pdirty) && navigator.sendBeacon) {
        try { if (navigator.sendBeacon(url, payload())) { dirty = false; pdirty = false; } } catch (e) {}
      }
    }
    window.addEventListener('pagehide', flush);
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'hidden') flush(); });

    // ── 시간 세기: 이 화면이 켜져 있는 동안만 ──
    let clock = null;
    function startClock(onTick) {
      if (clock) return;
      let last = Date.now();
      clock = setInterval(() => {
        const now = Date.now();
        elapsed += Math.min(Math.max(0, now - last) / 1000, MAX_STEP);
        last = now;
        hid.value = String(Math.floor(elapsed));
        pdirty = true;
        saveProgress(false);
        if (onTick) onTick(elapsed);
      }, 1000);
      if (onTick) onTick(elapsed);
    }

    if (dirty || pdirty) saveServer();
    return {
      save: saveServer,
      resumed: resumed,
      elapsed: () => elapsed,
      startClock: startClock,
      pos: () => pos,
      setPos: t => {
        if (!isFinite(t) || t <= pos) return;   // 앞으로만
        pos = t; pdirty = true; saveProgress(false);
      },
      clear: () => { store.del(key); store.del(pKey); },
    };
  }

  window.ExamAutosave = { init: init };
})();
