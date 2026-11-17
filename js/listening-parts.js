/*
 * IELTS Listening — 컴퓨터 시험(CD IELTS) 화면처럼 보여줌
 *   - 문제지를 4개 Part 로 나눠 한 번에 하나씩 (Part 1 = q1~q10 … Part 4 = q31~q40, 입력칸 name 기준)
 *     세트마다 "SECTION n" 표시가 제각각이라 문항 번호로 나눔. 입력칸이 없는 안내문·그림은 바로 뒤 문항의 Part 를 따름.
 *   - 각 Part 위에 회색 안내 상자 ("Part 1 / Listen and answer questions 1–10.")
 *   - 빈칸 입력칸 안에 문항 번호 표시 (원래 앞에 있던 굵은 번호는 숨김), 원래 "SECTION n" 제목은 숨김
 *   - 아래 고정 바: 현재 Part 의 문항 번호 (누르면 그 문항으로), 다른 Part 는 "Part 2  0 of 10", 오른쪽 ✓ = 제출
 *   - 오른쪽 아래 ← → : 이전 / 다음 문항
 * 사용: ListeningParts.init({ root, header, nav, arrows, onSubmit })
 */
(function () {
  const PARTS = 4;

  function qnum(el) {
    const m = /^q(\d+)/.exec(el.getAttribute('name') || '');
    return m ? +m[1] : null;
  }
  // 기본: 10문항씩 4 Part (Listening). Reading 은 init({ partOf }) 로 지문(page)별 Part 를 넘겨줌
  const defaultPartOf = n => Math.min(PARTS, Math.max(1, Math.ceil(n / 10)));
  let partOfQ = defaultPartOf;
  const FIELDS = 'input:not([type=hidden]),select,textarea';

  function partsIn(node) {
    const set = new Set();
    const els = node.matches && node.matches(FIELDS) ? [node] : [];
    node.querySelectorAll && els.push(...node.querySelectorAll(FIELDS));
    els.forEach(el => { const n = qnum(el); if (n) set.add(partOfQ(n)); });
    return set;
  }

  // container 의 자식마다 Part 지정. 여러 Part 에 걸친 자식은 그 안으로 들어가서 다시 나눔
  function assign(container) {
    // 바로 들어 있는 글자는 숨길 수 있게 span 으로 감쌈
    [...container.childNodes].forEach(n => {
      if (n.nodeType === 3 && n.textContent.trim()) {
        const s = document.createElement('span');
        container.insertBefore(s, n);
        s.appendChild(n);
      }
    });
    const kids = [...container.children];
    const info = kids.map(partsIn);
    kids.forEach((k, i) => {
      if (info[i].size > 1) { assign(k); return; }
      let p;
      if (info[i].size === 1) p = [...info[i]][0];
      else {
        const next = info.slice(i + 1).find(s => s.size > 0);
        const prev = info.slice(0, i).reverse().find(s => s.size > 0);
        p = next ? Math.min(...next) : prev ? Math.max(...prev) : 1;
      }
      k.dataset.part = p;
    });
  }

  const clean = s => s.replace(/\s+/g, ' ').trim();

  // 원래 문제지의 "SECTION 1", "SECTION 1 QUESTIONS 1-10" 제목 → 회색 Part 상자가 대신하므로 숨김
  function hideSectionTitles(root) {
    const re = /^SECTION\s*\d+\s*(?:[-:.,]?\s*QUESTIONS?\s*\d+\s*(?:-|–|—|to)\s*\d+)?\s*:?$/i;
    root.querySelectorAll('h1,h2,h3,h4,h5,b,strong,p,div,center,font,span,u').forEach(el => {
      if (el.closest('.lp-legacy-title') || el.querySelector(FIELDS)) return;
      if (re.test(clean(el.textContent))) el.classList.add('lp-legacy-title');
    });
  }

  // 빈칸 앞의 굵은 번호 (<b>1</b>) 는 숨기고 번호를 입력칸 안에 표시
  function numberBoxes(root) {
    root.querySelectorAll('input[type=text],input:not([type])').forEach(el => {
      const n = qnum(el); if (!n) return;
      el.placeholder = n;
      el.classList.add('lp-box');
      let p = el.previousSibling;
      // 공백이나 "£", "$" 같은 짧은 기호는 건너뜀 (<b>1</b> £ [빈칸])
      while (p && p.nodeType === 3 && /^[\s£$€¥#:]*$/.test(p.textContent)) p = p.previousSibling;
      if (p && p.nodeType === 1 && /^(B|STRONG|SPAN)$/.test(p.tagName)
        && clean(p.textContent).replace(/^\(|[).]$/g, '') === String(n)) p.classList.add('lp-num-hidden');
      fitBox(el);
    });
  }

  // 답이 길면 입력칸을 글자 길이만큼 넓힘 (기본 폭보다 줄어들지는 않음)
  let measureCtx;
  function fitBox(el) {
    if (!el.classList.contains('lp-box')) return;
    measureCtx = measureCtx || document.createElement('canvas').getContext('2d');
    const cs = getComputedStyle(el);
    measureCtx.font = cs.fontWeight + ' ' + cs.fontSize + ' ' + cs.fontFamily;
    const pad = cs.boxSizing !== 'border-box' ? 0 : parseFloat(cs.paddingLeft) + parseFloat(cs.paddingRight)
      + parseFloat(cs.borderLeftWidth) + parseFloat(cs.borderRightWidth);
    const w = el.value ? Math.ceil(measureCtx.measureText(el.value).width + pad + 8) : 0;
    if (w) el.style.setProperty('min-width', w + 'px', 'important');
    else el.style.removeProperty('min-width');
  }

  function init(o) {
    const root = o.root, nav = o.nav, header = o.header, arrows = o.arrows;
    if (!root) return null;
    partOfQ = o.partOf || defaultPartOf;
    const intro = o.intro || 'Listen and answer questions';
    const form = root.closest('form') || document;

    // 문항 번호 → 그 문항의 입력칸들
    const byQ = new Map();
    form.querySelectorAll(FIELDS).forEach(el => {
      const n = qnum(el); if (!n) return;
      if (!byQ.has(n)) byQ.set(n, []);
      byQ.get(n).push(el);
    });
    const qs = [...byQ.keys()].sort((a, b) => a - b);
    if (!qs.length) return null;
    const qsOf = p => qs.filter(n => partOfQ(n) === p);
    const parts = [1, 2, 3, 4, 5, 6].filter(p => qsOf(p).length);

    if (partsIn(root).size > 1) assign(root);
    hideSectionTitles(root);
    numberBoxes(root);

    const answered = n => (byQ.get(n) || []).some(el =>
      (el.type === 'radio' || el.type === 'checkbox') ? el.checked : el.value.trim() !== '');

    let curPart = parts[0], curQ = qsOf(curPart)[0];
    const tagged = [...root.querySelectorAll('[data-part]')];

    function renderNav() {
      nav.innerHTML = parts.map(p => {
        const list = qsOf(p);
        if (p !== curPart) {
          const done = list.filter(answered).length;
          return '<button type="button" class="lp-part" data-part-go="' + p + '"><b>Part ' + p + '</b>'
            + '<span class="lp-count">' + done + ' of ' + list.length + '</span></button>';
        }
        return '<div class="lp-part lp-part-on"><b>Part ' + p + '</b><span class="lp-qs">'
          + list.map(n => '<button type="button" data-q="' + n + '" class="lp-q'
            + (answered(n) ? ' lp-done' : '') + (n === curQ ? ' lp-cur' : '') + '">' + n + '</button>').join('')
          + '</span></div>';
      }).join('') + '<button type="button" class="lp-submit" data-submit title="Submit your answers">'
        + '<svg viewBox="0 0 24 24" width="22" height="22"><path d="M4 12.5l5 5L20 6.5" fill="none" stroke="currentColor" stroke-width="3"/></svg></button>';
      // 문제지 안의 현재 문항 번호에 테두리 (id="question-N" 이 있는 문제지)
      root.querySelectorAll('.lp-qnum-cur').forEach(el => el.classList.remove('lp-qnum-cur'));
      const qEl = document.getElementById('question-' + curQ);
      if (qEl && root.contains(qEl)) qEl.classList.add('lp-qnum-cur');
      const i = qs.indexOf(curQ);
      arrows.querySelector('[data-step="-1"]').disabled = i <= 0;
      arrows.querySelector('[data-step="1"]').disabled = i >= qs.length - 1;
    }

    function showPart(p) {
      curPart = p;
      const list = qsOf(p);
      header.innerHTML = '<b>Part ' + p + '</b><div>' + intro + ' ' + list[0] + '–' + list[list.length - 1] + '.</div>';
      tagged.forEach(el => el.classList.toggle('lp-hide', +el.dataset.part !== p));
      o.onPart && o.onPart(p);
    }

    function goQ(n, focus) {
      if (!byQ.has(n)) return;
      if (partOfQ(n) !== curPart) { showPart(partOfQ(n)); window.scrollTo(0, 0); }
      curQ = n;
      renderNav();
      if (focus) {
        const els = byQ.get(n);
        const el = els.find(e => e.checked) || els[0];
        el.scrollIntoView({ block: 'center', behavior: 'smooth' });
        el.focus({ preventScroll: true });
      }
    }

    nav.addEventListener('click', e => {
      const q = e.target.closest('[data-q]'), pg = e.target.closest('[data-part-go]');
      if (q) goQ(+q.dataset.q, true);
      else if (pg) goQ(qsOf(+pg.dataset.partGo)[0], true);
      else if (e.target.closest('[data-submit]') && o.onSubmit) o.onSubmit();
    });
    arrows.addEventListener('click', e => {
      const b = e.target.closest('[data-step]'); if (!b || b.disabled) return;
      const i = qs.indexOf(curQ) + +b.dataset.step;
      if (i >= 0 && i < qs.length) goQ(qs[i], true);
    });
    form.addEventListener('focusin', e => {
      const n = qnum(e.target);
      if (n && n !== curQ) { curQ = n; renderNav(); }
    });
    form.addEventListener('input', e => { fitBox(e.target); renderNav(); });
    form.addEventListener('change', renderNav);

    showPart(curPart);
    renderNav();
    return { goQ: goQ };
  }

  window.ListeningParts = { init: init };
})();
