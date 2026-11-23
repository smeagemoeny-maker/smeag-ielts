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
   * 형광펜 + 메모 (실제 CD IELTS 처럼)
   *   지문·문제 글자를 끌어 고르면 [Note] [Highlight] 버튼, 칠한 곳을 누르면 [Note] [Clear] 버튼
   *   <mark class="rd-hl" data-hl="N"> 로 감쌈 — 여러 문단에 걸친 선택은 같은 N 으로 묶어 한 번에 지움
   *   Note: 오른쪽 Notes 칸에 "Part N + 고른 글" 카드가 생기고 거기에 메모를 씀 (위 막대 Notes 아이콘으로 열고 닫음). 답안지에는 들어가지 않음
   */
  let notesApi = null;

  function esc(s) { return String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]); }

  function initHighlighter(root) {
    const SKIP = 'input,select,textarea,button,option,script,style,.rd-divider';
    const SHORT = 90;   // 카드에 처음 보여 줄 글자 수
    let seq = 0, pending = null, target = null, active = null;
    const notes = new Map();   // id → { part, quote, text, noteOnly, more, card }

    const pop = document.createElement('div');
    pop.className = 'rd-hl-pop';
    pop.innerHTML = '<button type="button" data-act="note"><span class="rd-hl-ico rd-hl-ico-note"></span>Note</button>'
      + '<button type="button" data-act="hl"><span class="rd-hl-ico"></span>Highlight</button>'
      + '<button type="button" data-act="clear"><span class="rd-hl-ico rd-hl-ico-x"></span>Clear</button>';
    document.body.appendChild(pop);

    // 오른쪽 Notes 칸
    const panel = document.createElement('aside');
    panel.className = 'rd-notes';
    panel.hidden = true;
    panel.innerHTML = '<div class="rd-notes-head"><span>Notes</span><button type="button" class="rd-notes-x" title="Close">'
      + '<svg viewBox="0 0 24 24" width="16" height="16"><path d="M5 5l14 14M19 5L5 19" stroke="currentColor" stroke-width="3.4"/></svg></button></div>'
      + '<div class="rd-notes-body"><div class="rd-notes-list"></div>'
      + '<div class="rd-notes-empty"><p class="rd-notes-big">Your private notes will show here</p><p>Select text to highlight or create a note.</p></div></div>';
    document.body.appendChild(panel);
    const list = panel.querySelector('.rd-notes-list');
    const empty = panel.querySelector('.rd-notes-empty');

    function marksOf(id) { return [...root.querySelectorAll('mark.rd-hl[data-hl="' + id + '"]')]; }

    function partOf(el) {
      const pages = [...root.querySelectorAll('.page')];
      const i = pages.indexOf(el && el.closest('.page'));
      return i >= 0 ? i + 1 : 1;
    }

    /** 칠한 곳 모양: 메모가 붙은 곳은 아래 점선, 메모 내용이 있으면 끝에 ✎ + 마우스를 올리면 내용 */
    function refreshMarks(id) {
      const n = notes.get(id);
      const text = n ? n.text.trim() : '';
      const ms = marksOf(id);
      ms.forEach((mk, i) => {
        mk.classList.toggle('rd-hl-noted', !!n);
        mk.classList.toggle('rd-note-only', !!n && n.noteOnly);
        mk.classList.toggle('rd-hl-active', !!n && id === active);
        mk.classList.toggle('rd-note-end', !!text && i === ms.length - 1);
        if (text) mk.title = text; else mk.removeAttribute('title');
      });
    }

    function renderEmpty() { empty.hidden = notes.size > 0; }

    function quoteHtml(n) {
      const q = n.quote.replace(/\s+/g, ' ').trim();
      if (q.length <= SHORT) return '<i>' + esc(q) + '</i>';
      return n.more
        ? '<i>' + esc(q) + '</i> <button type="button" class="rd-note-more">Show less</button>'
        : '<i>' + esc(q.slice(0, SHORT)) + '...</i> <button type="button" class="rd-note-more">Show more</button>';
    }

    function makeCard(id) {
      const n = notes.get(id);
      const card = document.createElement('div');
      card.className = 'rd-note-card';
      card.dataset.id = id;
      card.innerHTML = '<div class="rd-note-quote"><b>Part ' + n.part + '</b> <span></span></div>'
        + '<textarea rows="2" placeholder="Start typing your note" spellcheck="false" autocomplete="off"></textarea>'
        + '<div class="rd-note-actions"><button type="button" class="rd-note-del">Delete</button></div>';
      card.querySelector('.rd-note-quote span').innerHTML = quoteHtml(n);
      const area = card.querySelector('textarea');
      area.value = n.text;
      area.addEventListener('input', () => { n.text = area.value; refreshMarks(id); });
      n.card = card;
      list.appendChild(card);
    }

    function setActive(id) {
      const prev = active;
      active = id;
      notes.forEach((n, k) => n.card.classList.toggle('rd-on', k === id));
      if (prev && prev !== id) refreshMarks(prev);
      if (id) refreshMarks(id);
    }

    function isOpen() { return !panel.hidden; }
    function openPanel() { panel.hidden = false; document.body.classList.add('rd-notes-open'); renderEmpty(); }
    function closePanel() {
      panel.hidden = true;
      document.body.classList.remove('rd-notes-open');
      [...notes.keys()].forEach(id => { if (!notes.get(id).text.trim()) removeNote(id); });   // 빈 메모는 닫을 때 지움
      setActive(null);
    }

    function addNote(id, noteOnly) {
      if (!notes.has(id)) {
        const ms = marksOf(id);
        if (!ms.length) return;
        notes.set(id, { part: partOf(ms[0]), quote: ms.map(m => m.textContent).join(' '), text: '', noteOnly: noteOnly, more: false, card: null });
        makeCard(id);
      }
      openPanel();
      setActive(id);
      const card = notes.get(id).card;
      card.scrollIntoView({ block: 'nearest' });
      card.querySelector('textarea').focus();
    }

    function unwrap(id) {
      marksOf(id).forEach(mk => {
        const parent = mk.parentNode;
        while (mk.firstChild) parent.insertBefore(mk.firstChild, mk);
        parent.removeChild(mk);
        parent.normalize();
      });
    }

    /** 메모 지우기 — 메모 때문에 생긴 칠은 같이 지우고, 형광펜으로 칠한 곳은 칠만 남김 */
    function removeNote(id) {
      const n = notes.get(id);
      if (!n) return;
      n.card.remove();
      notes.delete(id);
      if (active === id) active = null;
      if (n.noteOnly) unwrap(id); else refreshMarks(id);
      renderEmpty();
    }

    function clear(id) {
      const n = notes.get(id);
      if (n) { n.noteOnly = true; removeNote(id); } else unwrap(id);
    }

    panel.addEventListener('click', e => {
      if (e.target.closest('.rd-notes-x')) { closePanel(); return; }
      const card = e.target.closest('.rd-note-card'); if (!card) return;
      const id = card.dataset.id, n = notes.get(id);
      if (e.target.closest('.rd-note-del')) { removeNote(id); return; }
      if (e.target.closest('.rd-note-more')) {
        n.more = !n.more;
        card.querySelector('.rd-note-quote span').innerHTML = quoteHtml(n);
        return;
      }
      setActive(id);
      if (e.target.closest('.rd-note-quote')) {   // 고른 글을 누르면 지문의 그 자리로
        const mk = marksOf(id)[0];
        if (mk && mk.offsetParent) mk.scrollIntoView({ block: 'center', behavior: 'smooth' });
      }
    });
    panel.addEventListener('focusin', e => { const c = e.target.closest('.rd-note-card'); if (c) setActive(c.dataset.id); });

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

    /** 칠한 묶음 번호 (새로 칠한 글자가 없고 이미 칠한 곳 안이면 그 번호, 아무것도 없으면 null) */
    function wrap(range) {
      const id = String(++seq);
      let made = 0;
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
        made++;
      });
      if (made) return id;
      const sc = range.startContainer;
      const inside = (sc.nodeType === 1 ? sc : sc.parentElement).closest('mark.rd-hl');
      return inside ? inside.dataset.hl : null;
    }

    // 팝업 버튼을 눌러도 선택이 풀리지 않게
    pop.addEventListener('mousedown', e => e.preventDefault());
    pop.addEventListener('click', e => {
      const b = e.target.closest('button'); if (!b) return;
      let noteId = null, fresh = false;
      if ((b.dataset.act === 'hl' || b.dataset.act === 'note') && pending) {
        const id = wrap(pending);
        fresh = id === String(seq);   // 메모 때문에 새로 칠한 곳 (메모를 지우면 칠도 지움)
        const sel = window.getSelection(); if (sel) sel.removeAllRanges();
        if (b.dataset.act === 'note') noteId = id;
      } else if (b.dataset.act === 'note' && target) {
        noteId = target;
      } else if (b.dataset.act === 'clear' && target) {
        clear(target);
      }
      hide();
      if (noteId) addNote(noteId, fresh);
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
          if (notes.has(target) && isOpen()) setActive(target);
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

    notesApi = { toggle: () => { if (isOpen()) closePanel(); else openPanel(); } };
  }

  // Part 를 바꿀 때 지문·문제 맨 위로
  function resetScroll(root) {
    root.querySelectorAll('.rd-pane').forEach(p => { p.scrollTop = 0; });
  }

  window.ReadingLayout = { build: build, resetScroll: resetScroll, toggleNotes: () => { if (notesApi) notesApi.toggle(); } };
})();
