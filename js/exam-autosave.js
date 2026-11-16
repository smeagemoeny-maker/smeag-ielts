/*
 * IELTS 응시 답안 자동 저장 (Listening · Reading · Writing) — 정전·인터넷 끊김·브라우저 꺼짐 대비
 *   1) 이 PC: 답을 고치는 즉시 브라우저(localStorage)에 저장 → 인터넷 없이도, 전원이 꺼져도 남음
 *   2) 서버: 고친 뒤 2초, 20초마다, 창을 닫을 때 서버(exam_drafts)에 저장 → 다른 PC 로 다시 로그인해도 이어서
 *   다시 열면 둘 중 최근 것을 되살림. 서버는 응시 화면을 열 때 만든 행만 고치므로 제출 뒤 늦게 온 저장은 무시됨.
 * 사용: ExamAutosave.init(form, { key, started, server: { answers, t } | null })
 */
(function () {
  const SERVER_DELAY = 2000, INTERVAL = 20000;
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

  function toFormData(data) {
    const fd = new FormData();
    fd.append('_autosave', '1');
    Object.keys(data).forEach(k => {
      if (Array.isArray(data[k])) data[k].forEach(v => fd.append(k + '[]', v));
      else fd.append(k, data[k]);
    });
    return fd;
  }

  function init(form, o) {
    if (!form || !o || !o.key) return null;
    const key = o.key + ':' + o.started;          // 응시마다 다른 칸 (새로 시작하면 옛 답 안 씀)
    const url = location.href;

    // 같은 학생·세트·영역의 지난 응시 임시 답안은 지움
    store.keys().forEach(k => { if (k.indexOf(o.key + ':') === 0 && k !== key) store.del(k); });

    // ── 상태 표시 (남은 시간 아래) ──
    const status = document.createElement('div');
    status.className = 'as-status';
    status.style.cssText = 'font-size:11px;color:#666;margin-top:2px;white-space:nowrap';
    const host = document.querySelector('.lp-status') || form;
    host.appendChild(status);
    function show(text, warn) { status.textContent = text; status.style.color = warn ? '#b45309' : '#666'; }
    const hhmm = () => new Date().toTimeString().slice(0, 5);

    // ── 되살리기: 이 PC 와 서버 중 최근 것 ──
    const local = store.get(key);
    const server = o.server && o.server.answers && !Array.isArray(o.server.answers) ? o.server : null;
    let restored = 0;
    if (local && (!server || local.t >= server.t)) restored = apply(form, local.answers);
    else if (server) restored = apply(form, server.answers);
    let dirty = !!(local && !local.synced && (!server || local.t > server.t));   // 서버에 못 보낸 것이 남아 있으면 곧 보냄
    show(restored ? '✓ Your answers were restored' : '✓ Autosave on');

    // ── 저장 ──
    let timer = null, sending = false;
    function saveLocal(synced) {
      const ok = store.set(key, { answers: collect(form), t: Date.now(), synced: !!synced });
      return ok;
    }
    async function saveServer() {
      clearTimeout(timer); timer = null;
      if (sending || !dirty) return;
      if (navigator.onLine === false) { show('Saved on this computer (offline)', true); return; }
      sending = true;
      dirty = false;
      show('Saving…');
      try {
        const res = await fetch(url, { method: 'POST', body: toFormData(collect(form)), credentials: 'same-origin', cache: 'no-store' });
        const j = res.ok ? await res.json().catch(() => null) : null;
        if (j && j.ok) {
          const rec = store.get(key); if (rec) { rec.synced = true; store.set(key, rec); }
          show('✓ All answers saved ' + hhmm());
        } else {
          dirty = true;
          show('Saved on this computer', true);
        }
      } catch (e) {
        dirty = true;
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
    setInterval(() => { if (dirty) saveServer(); }, INTERVAL);
    window.addEventListener('online', () => { if (dirty) saveServer(); });

    // 창을 닫거나 다른 화면으로 갈 때 마지막으로 한 번 더 (서버로는 sendBeacon)
    function flush() {
      saveLocal(!dirty);
      if (dirty && navigator.sendBeacon) {
        try { if (navigator.sendBeacon(url, toFormData(collect(form)))) dirty = false; } catch (e) {}
      }
    }
    window.addEventListener('pagehide', flush);
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'hidden') flush(); });

    if (dirty) saveServer();
    return { save: saveServer, clear: () => store.del(key) };
  }

  window.ExamAutosave = { init: init };
})();
