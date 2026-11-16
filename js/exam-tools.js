/*
 * IELTS 응시 화면 (Listening·Reading·Writing) 위 막대 오른쪽 도구 — 컴퓨터 시험(CD IELTS) 처럼
 *   📶 인터넷 연결 상태 : 끊기면 빨간 표시 + 안내 줄. 끊긴 동안 제출하면 막았다가 다시 연결되면 자동 제출 (답안 보호)
 *   ☰  Options         : Go to submission page (푼 문항 수 확인 후 제출) / Contrast (글자·배경 색) / Text size (글자 크기)
 *   ✎  Notes           : 메모장 (제출되지 않음, 이 시험 응시 동안만 이 브라우저에 보관, 제출하면 지움)
 *   Contrast · Text size 는 이 PC 브라우저에 기억 (다음 섹션에서도 그대로)
 * 사용: ExamTools.mount({ form, top, key, beforeSend })
 *   beforeSend = 실제로 보내기 직전에 할 일 (임시 저장 지우기 등). 연결이 끊겨 기다리는 동안에는 실행하지 않음
 */
(function () {
  const PREF = 'ielts-exam-prefs';
  const CONTRASTS = [
    { id: '', label: 'Black on white' },
    { id: 'ct-wb', label: 'White on black' },
    { id: 'ct-yb', label: 'Yellow on black' },
  ];
  const SIZES = [
    { id: '', label: 'Standard' },
    { id: 'ts-1', label: 'Large' },
    { id: 'ts-2', label: 'Extra large' },
  ];
  const ICON = {
    wifi: '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M2 8.5a15 15 0 0 1 20 0"/><path d="M5.5 12.2a10 10 0 0 1 13 0"/><path d="M9 15.8a5 5 0 0 1 6 0"/><circle cx="12" cy="19.3" r="1.4" fill="currentColor" stroke="none"/><path class="et-slash" d="M3 3l18 18"/></svg>',
    menu: '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.6"><path d="M3 6h18M3 12h18M3 18h18"/></svg>',
    note: '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"><path d="M20 13v7H4V4h8"/><path d="M17.5 2.8l3.7 3.7L12 15.7l-4.6.9.9-4.6z"/></svg>',
    send: '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4z"/></svg>',
    contrast: '<svg viewBox="0 0 24 24" width="20" height="20"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 3a9 9 0 0 1 0 18z" fill="currentColor"/></svg>',
    zoom: '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="10" cy="10" r="7"/><path d="M15 15l6 6M10 7v6M7 10h6"/></svg>',
    chev: '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="3.2"><path d="M9 5l7 7-7 7"/></svg>',
    back: '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="3.2"><path d="M15 5l-7 7 7 7"/></svg>',
    close: '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="3.6"><path d="M5 5l14 14M19 5L5 19"/></svg>',
    check: '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="3"><path d="M4 12.5l5 5L20 6.5"/></svg>',
  };

  function store(fn) { try { return fn(window.localStorage); } catch (e) { return null; } }
  function esc(s) { return String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c])); }

  // 화면 설정은 다른 페이지(안내 화면 등)에서도 쓸 수 있게 따로
  function prefs() { return store(s => JSON.parse(s.getItem(PREF) || '{}')) || {}; }
  function applyPrefs(p) {
    const b = document.body;
    CONTRASTS.forEach(c => c.id && b.classList.remove(c.id));
    SIZES.forEach(c => c.id && b.classList.remove(c.id));
    if (p.contrast) b.classList.add(p.contrast);
    if (p.size) b.classList.add(p.size);
  }
  function setPref(k, v) {
    const p = prefs(); p[k] = v;
    store(s => s.setItem(PREF, JSON.stringify(p)));
    applyPrefs(p);
  }

  // 푼 문항 수: q1~q40 (Listening·Reading) 또는 task1·task2 (Writing)
  function progress(form) {
    const all = new Map();
    form.querySelectorAll('input:not([type=hidden]),select,textarea').forEach(el => {
      const name = (el.getAttribute('name') || '').replace(/\[\]$/, '');
      if (!/^(q\d+|task\d)$/.test(name)) return;
      const done = (el.type === 'radio' || el.type === 'checkbox') ? el.checked : el.value.trim() !== '';
      all.set(name, (all.get(name) || false) || done);
    });
    let done = 0; all.forEach(v => { if (v) done++; });
    return { done: done, total: all.size, writing: [...all.keys()].some(k => k.startsWith('task')) };
  }

  function mount(o) {
    const form = o.form, top = o.top;
    const noteKey = o.key ? 'ielts-notes:' + o.key : null;
    applyPrefs(prefs());

    // ── 위 막대 아이콘 ──
    const bar = document.createElement('div');
    bar.className = 'et-icons';
    bar.innerHTML = '<button type="button" class="et-ico et-wifi" data-act="wifi" title="Internet connection">' + ICON.wifi + '</button>'
      + '<button type="button" class="et-ico" data-act="menu" title="Options">' + ICON.menu + '</button>'
      + '<button type="button" class="et-ico" data-act="notes" title="Notes">' + ICON.note + '</button>'
      + '<div class="et-pop" hidden></div>';
    top.appendChild(bar);
    const pop = bar.querySelector('.et-pop');

    // ── 연결 끊김 안내 줄 ──
    const banner = document.createElement('div');
    banner.className = 'et-offline';
    banner.hidden = true;
    if (getComputedStyle(top).position === 'static') top.style.position = 'relative';
    if (!top.style.zIndex) top.style.zIndex = '40';
    top.appendChild(banner);   // 위 막대 바로 아래에 겹쳐 띄움 (화면이 밀리지 않게)

    // ── Options 화면 ──
    const panel = document.createElement('div');
    panel.className = 'et-panel';
    panel.hidden = true;
    document.body.appendChild(panel);

    // ── Notes ──
    const notes = document.createElement('aside');
    notes.className = 'et-notes';
    notes.hidden = true;
    notes.innerHTML = '<div class="et-notes-head"><b>Notes</b><button type="button" data-act="notes-close" title="Close">' + ICON.close + '</button></div>'
      + '<p>Your notes are not marked and are not submitted.</p>'
      + '<textarea spellcheck="false" autocomplete="off" aria-label="Notes"></textarea>';
    document.body.appendChild(notes);
    const noteText = notes.querySelector('textarea');
    noteText.removeAttribute('name');   // 제출 대상 아님
    if (noteKey) noteText.value = store(s => s.getItem(noteKey)) || '';
    noteText.addEventListener('input', () => { if (noteKey) store(s => s.setItem(noteKey, noteText.value)); });

    // ── 연결 상태 ──
    let pending = false;
    function online() { return navigator.onLine !== false; }
    function renderNet() {
      const on = online();
      bar.querySelector('.et-wifi').classList.toggle('et-off', !on);
      bar.querySelector('.et-wifi').title = on ? 'Internet connection: connected' : 'Internet connection: not connected';
      banner.hidden = on;
      banner.textContent = pending
        ? 'No internet connection. Your answers are safe on this screen and will be submitted automatically when the connection returns. Do not close this window.'
        : 'No internet connection. Keep working — your answers stay on this screen. Do not close this window.';
      if (!pop.hidden && pop.dataset.kind === 'wifi') showWifi();
      if (on && pending) { pending = false; doSubmit(); }
    }
    window.addEventListener('online', renderNet);
    window.addEventListener('offline', renderNet);

    // 모든 제출 (✓ 버튼, 시간 끝, 음원 끝) 이 form.submit() 을 거치므로 여기서 연결 확인 + 메모 지움
    const realSubmit = HTMLFormElement.prototype.submit;
    function doSubmit() {
      if (!online()) { pending = true; renderNet(); return; }
      if (noteKey) store(s => s.removeItem(noteKey));
      if (o.beforeSend) try { o.beforeSend(); } catch (e) { /* 임시 저장 지우기 실패는 무시 */ }
      realSubmit.call(form);
    }
    form.submit = doSubmit;

    function showWifi() {
      const on = online();
      pop.dataset.kind = 'wifi';
      pop.innerHTML = '<b>Internet connection</b><div class="' + (on ? 'et-ok' : 'et-bad') + '">' + (on ? 'Connected' : 'Not connected') + '</div>';
      pop.hidden = false;
    }

    // ── Options 화면 그리기 ──
    function row(act, icon, label, extra) {
      return '<button type="button" class="et-row ' + (extra || '') + '" data-act="' + act + '"><span class="et-row-ico">' + icon + '</span>'
        + '<span class="et-row-label">' + label + '</span><span class="et-row-go">' + ICON.chev + '</span></button>';
    }
    function frame(title, body, back) {
      panel.innerHTML = '<div class="et-panel-in">'
        + '<div class="et-panel-head">' + (back ? '<button type="button" class="et-back" data-act="menu" title="Back">' + ICON.back + '</button>' : '')
        + '<h2>' + title + '</h2></div>' + body + '</div>'
        + '<button type="button" class="et-close" data-act="close" title="Close">' + ICON.close + '</button>';
      panel.hidden = false;
      const f = panel.querySelector('.et-panel-in .et-row, .et-panel-in .et-btn');
      if (f) f.focus();
    }
    function showMenu() {
      frame('Options', '<div class="et-group">' + row('submit-page', ICON.send, 'Go to submission page', 'et-primary') + '</div>'
        + '<div class="et-group">' + row('contrast', ICON.contrast, 'Contrast') + row('size', ICON.zoom, 'Text size') + '</div>');
    }
    function showChoice(title, list, cur, act) {
      frame(title, '<div class="et-group">' + list.map(c =>
        '<button type="button" class="et-row et-choice' + (c.id === cur ? ' et-on' : '') + '" data-act="' + act + '" data-val="' + c.id + '">'
        + '<span class="et-row-label et-sample ' + (act === 'set-contrast' ? 'et-sample-' + (c.id || 'bw') : '') + '">' + esc(c.label) + '</span>'
        + '<span class="et-row-go">' + (c.id === cur ? ICON.check : '') + '</span></button>').join('') + '</div>', true);
    }
    function showSubmitPage() {
      const p = progress(form);
      const left = p.total - p.done;
      const what = p.writing ? 'tasks' : 'questions';
      frame('Submission', '<div class="et-group et-submit-box">'
        + '<p class="et-big">You have answered <b>' + p.done + '</b> of <b>' + p.total + '</b> ' + what + '.</p>'
        + (left ? '<p class="et-warn">' + left + ' ' + (left === 1 ? what.slice(0, -1) : what) + ' not answered yet.</p>' : '')
        + '<p>Once you submit, you cannot change your answers.</p>'
        + '<div class="et-submit-actions"><button type="button" class="et-btn" data-act="close">Continue the test</button>'
        + '<button type="button" class="et-btn et-btn-red" data-act="submit-now">' + ICON.send + ' Submit and finish</button></div>'
        + '</div>', true);
    }

    function closeAll() { panel.hidden = true; pop.hidden = true; }

    document.addEventListener('click', e => {
      let b = e.target.closest('[data-act]');
      if (b && !(bar.contains(b) || panel.contains(b) || notes.contains(b))) b = null;   // 다른 화면 요소의 data-act 는 무시
      if (!b) { if (!e.target.closest('.et-pop')) pop.hidden = true; return; }
      const act = b.dataset.act;
      if (act !== 'wifi') pop.hidden = true;
      if (act === 'wifi') { if (pop.hidden) showWifi(); else pop.hidden = true; }
      else if (act === 'menu') showMenu();
      else if (act === 'close') closeAll();
      else if (act === 'notes') { notes.hidden = !notes.hidden; document.body.classList.toggle('et-notes-open', !notes.hidden); if (!notes.hidden) noteText.focus(); }
      else if (act === 'notes-close') { notes.hidden = true; document.body.classList.remove('et-notes-open'); }
      else if (act === 'submit-page') showSubmitPage();
      else if (act === 'contrast') showChoice('Contrast', CONTRASTS, prefs().contrast || '', 'set-contrast');
      else if (act === 'size') showChoice('Text size', SIZES, prefs().size || '', 'set-size');
      else if (act === 'set-contrast') { setPref('contrast', b.dataset.val); showChoice('Contrast', CONTRASTS, b.dataset.val, 'set-contrast'); }
      else if (act === 'set-size') { setPref('size', b.dataset.val); showChoice('Text size', SIZES, b.dataset.val, 'set-size'); }
      else if (act === 'submit-now') { closeAll(); doSubmit(); }
    });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeAll(); });

    renderNet();
    return { openSubmission: showSubmitPage };
  }

  window.ExamTools = { mount: mount, applyPrefs: () => applyPrefs(prefs()) };
})();
