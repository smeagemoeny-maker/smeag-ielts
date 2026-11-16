/*
 * IELTS Reading — 컴퓨터 시험(CD IELTS) 모양: 지문 3개 = Part 1~3, 왼쪽 지문 / 오른쪽 문제, 가운데 막대를 끌어 폭 조절
 *   문제지 구조 (60세트 공통): .page × 3 → 그 안 flex div → 반쪽(md:w-1/2) 2개 [지문, 문제]
 * 사용: const partOf = ReadingLayout.build(root)  → ListeningParts.init({ ..., partOf, intro: 'Read the text and answer questions' })
 */
(function () {
  let ratio = 50;   // 왼쪽 지문 폭 (%), 모든 Part 공통

  function qnum(el) {
    const m = /^q(\d+)/.exec(el.getAttribute('name') || '');
    return m ? +m[1] : null;
  }

  function build(root) {
    const pages = [...root.querySelectorAll('.page')];
    const partByQ = new Map();
    const splits = [];

    pages.forEach((page, i) => {
      page.querySelectorAll('input,select,textarea').forEach(el => {
        const n = qnum(el);
        if (n && !partByQ.has(n)) partByQ.set(n, i + 1);
      });
      const halves = [...page.querySelectorAll('[class*="md:w-1/2"]')].filter(h => h.closest('.page') === page);
      if (halves.length < 2) return;
      const holder = halves[0].parentElement;

      const split = document.createElement('div');
      split.className = 'rd-split';
      split.innerHTML = '<div class="rd-pane rd-passage"></div>'
        + '<div class="rd-divider" title="Drag to resize"><span>&#x27F7;</span></div>'
        + '<div class="rd-pane rd-questions"></div>';
      const [left, , right] = split.children;
      while (halves[0].firstChild) left.appendChild(halves[0].firstChild);
      while (halves[1].firstChild) right.appendChild(halves[1].firstChild);
      holder.replaceWith(split);
      splits.push(split);
    });

    function apply() {
      splits.forEach(s => { s.children[0].style.flexBasis = ratio + '%'; });
    }

    // 가운데 막대 끌기
    let drag = null;
    root.addEventListener('mousedown', e => {
      const d = e.target.closest('.rd-divider'); if (!d) return;
      drag = d.parentElement; e.preventDefault();
      document.body.classList.add('rd-dragging');
    });
    window.addEventListener('mousemove', e => {
      if (!drag) return;
      const r = drag.getBoundingClientRect();
      ratio = Math.min(80, Math.max(20, (e.clientX - r.left) / r.width * 100));
      apply();
    });
    window.addEventListener('mouseup', () => { drag = null; document.body.classList.remove('rd-dragging'); });
    apply();
    initHighlighter(root);

    const last = pages.length || 1;
    return n => partByQ.get(n) || Math.min(last, Math.max(1, Math.ceil(n / 14)));
  }

  /*
   * 형광펜 (실제 CD IELTS 처럼): 지문·문제 글자를 끌어 고르면 [Highlight] 버튼, 칠한 곳을 누르면 [Clear] 버튼
   *   <mark class="rd-hl" data-hl="N"> 로 감쌈 — 여러 문단에 걸친 선택은 같은 N 으로 묶어 한 번에 지움
   */
  function initHighlighter(root) {
    const SKIP = 'input,select,textarea,button,option,script,style,.rd-divider';
    let seq = 0, pending = null, target = null;

    const pop = document.createElement('div');
    pop.className = 'rd-hl-pop';
    pop.innerHTML = '<button type="button" data-act="hl"><span class="rd-hl-ico"></span>Highlight</button>'
      + '<button type="button" data-act="clear"><span class="rd-hl-ico rd-hl-ico-x"></span>Clear</button>';
    document.body.appendChild(pop);

    function hide() { pop.style.display = 'none'; pending = target = null; }
    function show(rect, mode) {
      pop.querySelector('[data-act="hl"]').style.display = mode === 'hl' ? '' : 'none';
      pop.querySelector('[data-act="clear"]').style.display = mode === 'clear' ? '' : 'none';
      pop.style.display = 'flex';
      const w = pop.offsetWidth, h = pop.offsetHeight;
      let top = rect.bottom + 6;
      if (top + h > window.innerHeight - 70) top = rect.top - h - 6;
      pop.style.top = Math.max(4, top) + 'px';
      pop.style.left = Math.min(window.innerWidth - w - 4, Math.max(4, rect.left + rect.width / 2 - w / 2)) + 'px';
    }

    function inPane(node) {
      const el = node && (node.nodeType === 1 ? node : node.parentElement);
      return el && root.contains(el) && el.closest('.rd-pane');
    }

    function wrap(range) {
      const id = String(++seq);
      let base = range.commonAncestorContainer;
      if (base.nodeType !== 1) base = base.parentElement;
      const nodes = [];
      const walker = document.createTreeWalker(base, NodeFilter.SHOW_TEXT, {
        acceptNode: n => range.intersectsNode(n) && n.nodeValue.trim() && !n.parentElement.closest(SKIP)
          ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT
      });
      while (walker.nextNode()) nodes.push(walker.currentNode);
      nodes.forEach(n => {
        const start = n === range.startContainer ? range.startOffset : 0;
        const end = n === range.endContainer ? range.endOffset : n.nodeValue.length;
        if (start >= end) return;
        if (n.parentElement.closest('.rd-hl')) return;   // 이미 칠한 곳
        let mid = n;
        if (end < mid.nodeValue.length) mid.splitText(end);
        if (start > 0) mid = mid.splitText(start);
        if (!mid.nodeValue.trim()) return;
        const mk = document.createElement('mark');
        mk.className = 'rd-hl';
        mk.dataset.hl = id;
        mid.parentNode.insertBefore(mk, mid);
        mk.appendChild(mid);
      });
    }

    function clear(id) {
      root.querySelectorAll('mark.rd-hl[data-hl="' + id + '"]').forEach(mk => {
        const parent = mk.parentNode;
        while (mk.firstChild) parent.insertBefore(mk.firstChild, mk);
        parent.removeChild(mk);
        parent.normalize();
      });
    }

    // 팝업 버튼을 눌러도 선택이 풀리지 않게
    pop.addEventListener('mousedown', e => e.preventDefault());
    pop.addEventListener('click', e => {
      const b = e.target.closest('button'); if (!b) return;
      if (b.dataset.act === 'hl' && pending) {
        wrap(pending);
        const sel = window.getSelection(); if (sel) sel.removeAllRanges();
      } else if (b.dataset.act === 'clear' && target) {
        clear(target);
      }
      hide();
    });

    root.addEventListener('mouseup', e => {
      if (e.button !== 0 || document.body.classList.contains('rd-dragging')) return;
      if (e.target.closest(SKIP)) return;
      setTimeout(() => {   // 선택이 확정된 뒤 확인
        const sel = window.getSelection();
        if (sel && sel.rangeCount && !sel.isCollapsed) {
          const r = sel.getRangeAt(0);
          if (inPane(r.startContainer) && inPane(r.endContainer) && r.toString().trim()) {
            pending = r.cloneRange();
            target = null;
            show(r.getBoundingClientRect(), 'hl');
            return;
          }
        }
        const mk = e.target.closest('mark.rd-hl');
        if (mk) {
          target = mk.dataset.hl;
          pending = null;
          show(mk.getBoundingClientRect(), 'clear');
          return;
        }
        hide();
      }, 0);
    });

    document.addEventListener('mousedown', e => { if (!pop.contains(e.target)) hide(); });
    root.addEventListener('scroll', hide, true);
    window.addEventListener('resize', hide);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') hide(); });
  }

  // Part 를 바꿀 때 지문·문제 맨 위로
  function resetScroll(root) {
    root.querySelectorAll('.rd-pane').forEach(p => { p.scrollTop = 0; });
  }

  window.ReadingLayout = { build: build, resetScroll: resetScroll };
})();
