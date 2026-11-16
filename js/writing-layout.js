/*
 * IELTS Writing — 컴퓨터 시험(CD IELTS) 모양
 *   - Task 1 = Part 1, Task 2 = Part 2. 한 번에 하나씩, 왼쪽 문제 / 오른쪽 답안 (가운데 막대를 끌어 폭 조절)
 *   - 회색 안내 상자: "Part 1 / You should spend about 20 minutes on this task. Write at least 150 words."
 *     (원래 문제지의 "WRITING TASK n" 제목·시간·단어 수 안내는 상자가 대신하므로 숨김)
 *   - 답안 아래 "Words: n", 아래 고정 바 "Part 1  0 of 1" (쓴 Part 는 1 of 1), ← → 로 Part 이동, ✓ 제출
 *   - 새로고침·컴퓨터 꺼짐 대비: 쓰는 중인 답안을 이 브라우저에 임시 저장 (제출하면 지움)
 *   문제지 구조 (60세트 공통): .page × 2 → 그 안 flex div → 반쪽(md:w-1/2) 2개 [문제, textarea]
 * 사용: WritingLayout.init({ root, header, nav, arrows, draftKey, onSubmit })
 */
(function () {
  const INFO = {
    1: { min: 20, words: 150 },
    2: { min: 40, words: 250 },
  };
  const clean = s => s.replace(/\s+/g, ' ').trim();
  const words = s => (s.trim() ? s.trim().split(/\s+/).length : 0);

  function store(fn) { try { return fn(window.localStorage); } catch (e) { return null; } }

  function init(o) {
    const root = o.root, nav = o.nav, header = o.header, arrows = o.arrows;
    const pages = [...root.querySelectorAll('.page')];
    const parts = [];   // { n, page, ta, out }
    let ratio = 50;

    pages.forEach((page, i) => {
      const ta = page.querySelector('textarea');
      if (!ta) return;
      const n = /task(\d)/.exec(ta.name) ? +/task(\d)/.exec(ta.name)[1] : i + 1;
      const halves = [...page.querySelectorAll('[class*="md:w-1/2"]')].filter(h => h.closest('.page') === page);

      const split = document.createElement('div');
      split.className = 'wr-split';
      split.innerHTML = '<div class="wr-pane wr-prompt"></div>'
        + '<div class="wr-divider" title="Drag to resize"><span>&#x27F7;</span></div>'
        + '<div class="wr-pane wr-answer"><div class="wr-words">Words: <span>0</span></div></div>';
      const [left, , right] = split.children;
      const promptSrc = halves[0] && !halves[0].contains(ta) ? halves[0] : null;
      if (promptSrc) while (promptSrc.firstChild) left.appendChild(promptSrc.firstChild);
      right.insertBefore(ta, right.firstChild);
      if (halves.length) halves[0].parentElement.replaceWith(split);
      else page.appendChild(split);

      // 제목·시간·단어 수 안내는 회색 상자로 옮겼으니 숨김
      left.querySelectorAll('h1,h2,h3,h4,p,div,b,i,span').forEach(el => {
        const t = clean(el.textContent);
        if (/^WRITING\s*TASK\s*\d\s*:?$/i.test(t)
          || /^You should spend about \d+ minutes on this task\.?$/i.test(t)
          || /^(You should )?write at least \d+ words\.?$/i.test(t)) el.classList.add('wr-hidden');
      });

      ta.className = 'wr-text';
      ta.removeAttribute('rows');
      ta.placeholder = '';
      ta.spellcheck = false;
      ta.setAttribute('autocomplete', 'off');
      ta.setAttribute('autocorrect', 'off');
      ta.setAttribute('autocapitalize', 'off');
      parts.push({ n: n, page: page, ta: ta, out: right.querySelector('.wr-words span') });
    });
    if (!parts.length) return null;

    // 임시 저장된 답안 되살리기
    const key = o.draftKey ? 'ielts-writing-draft:' + o.draftKey : null;
    if (key) {
      const saved = store(s => JSON.parse(s.getItem(key) || 'null'));
      if (saved) parts.forEach(p => { if (!p.ta.value && saved[p.ta.name]) p.ta.value = saved[p.ta.name]; });
    }
    function save() {
      if (!key) return;
      const data = {};
      parts.forEach(p => { data[p.ta.name] = p.ta.value; });
      store(s => s.setItem(key, JSON.stringify(data)));
    }

    let cur = 0;

    function renderNav() {
      nav.innerHTML = parts.map((p, i) => {
        const done = p.ta.value.trim() ? 1 : 0;
        if (i !== cur) {
          return '<button type="button" class="lp-part" data-go="' + i + '"><b>Part ' + p.n + '</b>'
            + '<span class="lp-count">' + done + ' of 1</span></button>';
        }
        return '<div class="lp-part lp-part-on wr-part-on' + (done ? ' wr-done' : '') + '"><b>Part ' + p.n + '</b></div>';
      }).join('') + '<button type="button" class="lp-submit" data-submit title="Submit your answers">'
        + '<svg viewBox="0 0 24 24" width="22" height="22"><path d="M4 12.5l5 5L20 6.5" fill="none" stroke="currentColor" stroke-width="3"/></svg></button>';
      arrows.querySelector('[data-step="-1"]').disabled = cur <= 0;
      arrows.querySelector('[data-step="1"]').disabled = cur >= parts.length - 1;
    }

    function count(p) { p.out.textContent = words(p.ta.value); }

    function show(i) {
      cur = Math.max(0, Math.min(parts.length - 1, i));
      const p = parts[cur], info = INFO[p.n] || INFO[1];
      header.innerHTML = '<b>Part ' + p.n + '</b><div>You should spend about ' + info.min
        + ' minutes on this task. Write at least ' + info.words + ' words.</div>';
      parts.forEach((q, j) => q.page.classList.toggle('wr-off', j !== cur));
      root.querySelectorAll('.wr-pane').forEach(el => { el.scrollTop = 0; });
      renderNav();
      p.ta.focus({ preventScroll: true });
    }

    parts.forEach(p => {
      count(p);
      p.ta.addEventListener('input', () => { count(p); save(); renderNav(); });
    });

    nav.addEventListener('click', e => {
      const g = e.target.closest('[data-go]');
      if (g) show(+g.dataset.go);
      else if (e.target.closest('[data-submit]') && o.onSubmit) o.onSubmit();
    });
    arrows.addEventListener('click', e => {
      const b = e.target.closest('[data-step]');
      if (b && !b.disabled) show(cur + +b.dataset.step);
    });

    // 가운데 막대 끌기 (두 Part 공통 폭)
    function apply() { root.querySelectorAll('.wr-prompt').forEach(el => { el.style.flexBasis = ratio + '%'; }); }
    let drag = null;
    root.addEventListener('mousedown', e => {
      const d = e.target.closest('.wr-divider'); if (!d) return;
      drag = d.parentElement; e.preventDefault();
      document.body.classList.add('wr-dragging');
    });
    window.addEventListener('mousemove', e => {
      if (!drag) return;
      const r = drag.getBoundingClientRect();
      ratio = Math.min(80, Math.max(20, (e.clientX - r.left) / r.width * 100));
      apply();
    });
    window.addEventListener('mouseup', () => { drag = null; document.body.classList.remove('wr-dragging'); });
    apply();

    show(0);
    return { clearDraft: () => key && store(s => s.removeItem(key)) };
  }

  window.WritingLayout = { init: init };
})();
