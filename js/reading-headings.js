/*
 * IELTS Reading — "List of Headings" 문제를 끌어다 놓기로 (컴퓨터 시험 CD IELTS 처럼)
 *   - 문제지에서 i, ii, iii … 로 번호가 붙은 제목 목록 상자를 찾아, 제목마다 끌 수 있는 카드로 바꿈
 *   - 그 문제의 빈칸(문항)은 카드를 놓는 칸으로 바뀜. 놓으면 원래 입력칸에 로마 숫자(i, ii …)가 들어가 그대로 채점·자동 저장됨
 *   - 한 제목은 한 번만: 놓은 제목은 목록에서 빠지고, 칸에서 목록으로 다시 끌거나 × 를 누르면 돌아옴. 칸끼리 옮기기도 됨
 *   - 마우스로 끌기 외에 "제목 클릭 → 칸 클릭" 으로도 놓을 수 있음
 *   - 제목 번호는 순서로 매김 (문제지에 "ili", "vi vi vi" 같은 오타가 있는 세트가 있어서)
 * 사용: ReadingHeadings.init(root)  — ListeningParts.init 보다 먼저 (문항 번호 바가 원래 입력칸을 그대로 씀)
 */
(function () {
  const ROMANS = ['i', 'ii', 'iii', 'iv', 'v', 'vi', 'vii', 'viii', 'ix', 'x', 'xi', 'xii', 'xiii', 'xiv', 'xv'];
  const isText = el => el.tagName === 'INPUT' && (!el.type || el.type === 'text');
  const clean = s => s.replace(/\s+/g, ' ').trim();
  const TITLE = /^\s*list of (headings?|questions)\s*:?/i;

  // 번호 토큰 정리: 문제지 오타 (ili → iii, vili → viii, × → x, X → x, "i." "(ii)") 를 맞춤
  function norm(tok) {
    return tok.toLowerCase().replace(/^\(/, '').replace(/[.)]+$/, '').replace(/×/g, 'x').replace(/[l1]/g, 'i');
  }

  // 상자 글을 i, ii, iii … (또는 1, 2, 3 …) 순서대로 잘라 제목 목록으로. 굵은 글씨·줄바꿈·<li> 상관없이 글만 봄
  //   (번호가 앞 제목 끝에 붙어 버린 "… robotics. vii There are …" 같은 오타도 순서대로 찾으므로 나뉨)
  // 안내문의 제목 수 "(i–xi)" / "1-7" → 11 / 7 (모르면 0)
  function expectedCount(box) {
    const t = (box.closest('.question') || box.parentElement).textContent;
    const m = t.match(/\(?\b(i|1)\s*[-–—]\s*([ivxl]+|\d{1,2})\b/i);
    if (!m) return 0;
    return m[1] === '1' ? +m[2] : ROMANS.indexOf(norm(m[2])) + 1;
  }

  // 먼저 번호를 정확히 따라 읽고, 안내문보다 적게 나오면 (GENERAL11 "vi vi vi" 오타) 되풀이 번호를 허용해 다시 읽음.
  // 다시 읽은 결과는 안내문 수와 꼭 맞을 때만 씀
  function parse(box) {
    const strict = parseWith(box, false);
    const want = expectedCount(box);
    if (want && strict.length < want) {
      const loose = parseWith(box, true);
      if (loose.length === want) return loose;
    }
    return strict;
  }

  function parseWith(box, allowRepeat) {
    const text = clean(box.textContent).replace(TITLE, '');
    const toks = text.split(' ');
    const arabic = toks[0] === '1' || toks[0] === '1.';
    const labels = arabic ? ROMANS.map((_, i) => String(i + 1)) : ROMANS;
    if (!toks.length || norm(toks[0]) !== labels[0] && !(arabic)) return [];
    const starts = [];   // [토큰 위치, 번호]
    let k = 0;
    toks.forEach((t, i) => {
      if (k >= labels.length) return;
      const n = arabic ? t.replace(/[.)]+$/, '') : norm(t);
      const next = toks[i + 1] || '';
      // 번호 다음은 제목 첫 글자 (대문자·따옴표·숫자). 제목 안의 소문자 단어와 헷갈리지 않게
      const head = i === 0 || /^[A-Z‘'"“(0-9]/.test(next);
      // 기대한 번호, 또는 (GENERAL11 "vi vi vi" 처럼) 앞 번호를 잘못 되풀이한 것 — 순서대로 번호를 다시 매김
      const at = ROMANS.indexOf(n);
      const repeat = allowRepeat && !arabic && k >= 2 && n.length > 1 && at >= 0 && at >= k - 3 && at < k;
      if (head && (n === labels[k] || repeat)) { starts.push([i, labels[k]]); k++; }
    });
    return starts.map(([at, value], j) => {
      let words = toks.slice(at + 1, j + 1 < starts.length ? starts[j + 1][0] : toks.length);
      words = words.filter(w => norm(w) !== value);                 // 제목 안에 끼어든 자기 번호 ("… some vii things …")
      let t = words.join(' ').trim();
      if (j === starts.length - 1) t = t.replace(/\s+Example\b.*$/i, '');   // 상자 안 "Example … Answer …" 줄
      return { value: value, text: t };
    }).filter(it => it.text);
  }

  // 제목 목록 상자 찾기: 그 안 글이 i, ii, iii … 로 3개 이상 나뉘고, 입력칸이 없고, 문제 안내문에 "heading" 이 있는 가장 작은 상자
  function findBoxes(root) {
    const cands = [...root.querySelectorAll('div,td,ul,ol,center,p')].filter(el =>
      !el.querySelector('input,select,textarea') && parse(el).length >= 3
      && /heading/i.test((el.closest('.question') || el.parentElement).textContent));
    return cands.filter(el => !cands.some(o => o !== el && el.contains(o)));
  }

  // 이 문제의 문항 범위: 상자 앞 안내문의 "Questions 14-19" / "boxes 14 - 20" 중 마지막 것
  function questionRange(box) {
    const block = box.closest('.question') || box.closest('.page') || box.parentElement;
    const r = document.createRange();
    r.setStart(block, 0);
    r.setEndBefore(box);
    const m = [...r.toString().matchAll(/(?:questions?|boxes?)\s*(\d{1,2})\s*(?:[-–—]|to|and)\s*(\d{1,2})/gi)].pop();
    return m ? [+m[1], +m[2]] : null;
  }

  // 상자 뒤 이 문제의 빈칸: 다음 "Questions n" 제목에서 멈춤. 안내문 범위가 있으면 그 번호만,
  // 범위가 틀려 하나도 없으면 (GENERAL4: 안내문은 29–33, 실제는 15–21) 이어지는 글 입력칸만
  function answerInputs(box) {
    const page = box.closest('.page') || box.closest('.rd-pane') || document.body;
    const els = [...page.querySelectorAll('input,select,textarea,h1,h2,h3,h4,b,strong')]
      .filter(el => box.compareDocumentPosition(el) & Node.DOCUMENT_POSITION_FOLLOWING && !box.contains(el));
    function collect(range) {
      const out = [];
      for (const el of els) {
        if (!/^(INPUT|SELECT|TEXTAREA)$/.test(el.tagName)) {
          if (out.length && /^\s*(section\s*\d+\s*)?questions?\s*\d/i.test(el.textContent)) break;
          continue;
        }
        const n = /^q(\d+)$/.test(el.name || '') ? +el.name.slice(1) : 0;
        if (range) {
          if (n > range[1]) break;
          if (n >= range[0] && isText(el)) out.push(el);
        } else {
          if (!isText(el) || !n) { if (out.length) break; continue; }
          out.push(el);
        }
      }
      return out;
    }
    const range = questionRange(box);
    const got = range ? collect(range) : [];
    return got.length ? got : collect(null);
  }

  const readItems = parse;

  function esc(s) { return String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c])); }

  // 끌어다 놓는 문제 첫 문항 바로 위에 안내 한 줄 ("Move your answer into the gap.")
  //   첫 빈칸이 있는 줄의 시작(앞의 <br>·블록 다음)을 찾아 그 앞에 넣음. 표 안이면 표 앞에
  function addHint(first) {
    if (!first || !first.parentNode) return;
    let at = first.closest('table') || first;
    if (at === first) {
      const BLOCK = /^(BR|DIV|P|TABLE|UL|OL|LI|H\d|CENTER)$/;
      while (at.previousSibling && !(at.previousSibling.nodeType === 1 && BLOCK.test(at.previousSibling.tagName))) at = at.previousSibling;
      if (!at.previousSibling && /^(P|LI)$/.test(at.parentNode.tagName)) at = at.parentNode;
    }
    const prev = at.previousElementSibling;
    if (prev && prev.classList.contains('dd-hint')) return;
    if (!document.getElementById('dd-hint-style')) {
      const st = document.createElement('style');
      st.id = 'dd-hint-style';
      st.textContent = '.dd-hint{margin:8px 0 6px;color:#1a5bb8;font-style:italic;font-weight:600}';
      document.head.appendChild(st);
    }
    const hint = document.createElement('div');
    hint.className = 'dd-hint';
    hint.textContent = 'Move your answer into the gap.';
    at.parentNode.insertBefore(hint, at);
  }

  let styled = false;
  function addStyle() {
    if (styled) return; styled = true;
    const st = document.createElement('style');
    st.textContent = ''
      + '.hd-list{border:1px solid #000;padding:10px 12px;margin:6px 0 14px;max-width:700px;background:#fff}'
      + '.hd-list-title{text-align:center;font-weight:700;margin-bottom:8px}'
      + '.hd-items{display:flex;flex-direction:column;gap:5px;min-height:30px}'
      + '.hd-chip{display:flex;gap:8px;align-items:baseline;padding:5px 10px;border:1px solid #8a8a8a;border-radius:3px;background:#fff;cursor:grab;user-select:none;line-height:1.35}'
      + '.hd-chip:hover{border-color:#1a5bb8;background:#f3f7fd}'
      + '.hd-chip b{min-width:28px;font-weight:700}'
      + '.hd-chip.hd-picked{outline:2px solid #1a5bb8;background:#e7f0fc}'
      + '.hd-chip.hd-dragging{opacity:.45}'
      + '.hd-list.hd-over{background:#f3f7fd}'
      // 원래 입력칸: 값만 들고 보이지 않게 (제자리에 크기 0 — 문항 이동 때 지문·문제 칸 안에서만 스크롤되도록 position 은 쓰지 않음)
      + '.legacy-content input.hd-input{display:inline-block!important;width:0!important;min-width:0!important;height:0!important;padding:0!important;margin:0!important;border:0!important;opacity:0!important;pointer-events:none;vertical-align:middle}'
      + '.hd-zone{display:inline-flex;align-items:center;gap:6px;vertical-align:middle;min-width:260px;max-width:100%;min-height:30px;margin:3px 6px;padding:2px 6px;border:1px dashed #8a8a8a;border-radius:3px;background:#fff;font-weight:700;color:#555;cursor:pointer}'
      + '.hd-zone.hd-empty{justify-content:center}'
      + '.hd-zone.hd-over,.hd-zone.hd-target{border:2px solid #1a5bb8;background:#eef4fd}'
      + '.hd-zone.hd-cur{border-color:#1a5bb8}'
      + '.hd-zone .hd-chip{flex:1;border:0;padding:3px 4px;background:none;font-weight:400;color:#000}'
      + '.hd-zone .hd-x{border:0;background:none;color:#888;font-size:16px;line-height:1;cursor:pointer;padding:0 4px}'
      + '.hd-zone .hd-x:hover{color:#c00}';
    document.head.appendChild(st);
  }

  function setup(box) {
    const items = readItems(box);
    const inputs = answerInputs(box);
    if (items.length < 3 || !inputs.length) return false;
    addStyle();
    const byVal = new Map(items.map(it => [it.value, it]));

    // 새 목록 상자 (원래 상자는 숨김)
    const titleEl = [...box.querySelectorAll('b,strong,center,p')].find(e => /list of heading/i.test(e.textContent));
    const list = document.createElement('div');
    list.className = 'hd-list';
    list.innerHTML = '<div class="hd-list-title">' + esc(titleEl ? clean(titleEl.textContent) : 'List of Headings') + '</div><div class="hd-items"></div>';
    box.after(list);
    box.style.display = 'none';
    const itemsEl = list.querySelector('.hd-items');

    // 빈칸 → 놓는 칸
    const zones = inputs.map(inp => {
      const n = +inp.name.slice(1);
      const z = document.createElement('span');
      z.className = 'hd-zone hd-empty';
      z.dataset.q = n;
      inp.after(z);
      inp.classList.add('hd-input');
      inp.readOnly = true;
      inp.tabIndex = -1;
      return { n: n, inp: inp, el: z };
    });

    const chip = it => '<span class="hd-chip" draggable="true" data-val="' + it.value + '"><b>' + it.value + '</b><span>' + esc(it.text) + '</span></span>';
    const val = z => (z.inp.value || '').trim().toLowerCase();

    function render() {
      const used = new Set(zones.map(val).filter(v => byVal.has(v)));
      itemsEl.innerHTML = items.filter(it => !used.has(it.value)).map(chip).join('');
      zones.forEach(z => {
        const it = byVal.get(val(z));
        z.el.classList.toggle('hd-empty', !it);
        z.el.innerHTML = it ? chip(it) + '<button type="button" class="hd-x" title="Remove">&times;</button>' : String(z.n);
      });
    }

    function put(z, value) {
      if (value) zones.forEach(o => { if (o !== z && val(o) === value) set(o, ''); });   // 한 제목은 한 칸에만
      set(z, value);
      render();
    }
    function set(z, value) {
      if (val(z) === value) return;
      z.inp.value = value;
      z.inp.dispatchEvent(new Event('input', { bubbles: true }));
      z.inp.dispatchEvent(new Event('change', { bubbles: true }));
    }

    // ── 마우스로 끌기 ──
    let dragVal = null, dragFrom = null;
    function zoneOf(el) { const z = el && el.closest && el.closest('.hd-zone'); return z ? zones.find(o => o.el === z) : null; }
    list.closest('.page, body').addEventListener('dragstart', e => {
      const c = e.target.closest && e.target.closest('.hd-chip');
      if (!c || !(list.contains(c) || zones.some(z => z.el.contains(c)))) return;
      dragVal = c.dataset.val; dragFrom = zoneOf(c);
      e.dataTransfer.effectAllowed = 'move';
      try { e.dataTransfer.setData('text/plain', dragVal); } catch (er) {}
      c.classList.add('hd-dragging');
    });
    document.addEventListener('dragend', () => { dragVal = dragFrom = null; document.querySelectorAll('.hd-over').forEach(x => x.classList.remove('hd-over')); render(); });
    zones.forEach(z => {
      z.el.addEventListener('dragover', e => { if (dragVal) { e.preventDefault(); z.el.classList.add('hd-over'); } });
      z.el.addEventListener('dragleave', () => z.el.classList.remove('hd-over'));
      z.el.addEventListener('drop', e => {
        e.preventDefault(); z.el.classList.remove('hd-over');
        if (!dragVal) return;
        const prev = val(z);
        if (dragFrom && dragFrom !== z) set(dragFrom, prev && byVal.has(prev) ? prev : '');   // 칸끼리 맞바꾸기
        put(z, dragVal);
      });
    });
    list.addEventListener('dragover', e => { if (dragVal && dragFrom) { e.preventDefault(); list.classList.add('hd-over'); } });
    list.addEventListener('dragleave', () => list.classList.remove('hd-over'));
    list.addEventListener('drop', e => { e.preventDefault(); list.classList.remove('hd-over'); if (dragFrom) { set(dragFrom, ''); render(); } });

    // ── 클릭으로 놓기 (제목 클릭 → 칸 클릭) ──
    let picked = null;
    function pick(v) {
      picked = v;
      list.querySelectorAll('.hd-chip').forEach(c => c.classList.toggle('hd-picked', c.dataset.val === v));
      zones.forEach(z => z.el.classList.toggle('hd-target', !!v));
    }
    list.addEventListener('click', e => {
      const c = e.target.closest('.hd-chip');
      pick(c && picked !== c.dataset.val ? c.dataset.val : null);
    });
    zones.forEach(z => z.el.addEventListener('click', e => {
      if (e.target.closest('.hd-x')) { set(z, ''); render(); pick(null); return; }
      if (picked) { put(z, picked); pick(null); }
      z.inp.focus({ preventScroll: true });   // 아래 문항 번호 바의 현재 문항 표시
    }));
    document.addEventListener('keydown', e => { if (e.key === 'Escape') pick(null); });

    addHint(inputs[0]);

    // 자동 저장에서 되살린 값 등 바깥에서 입력칸이 바뀌면 다시 그림
    inputs.forEach(inp => inp.addEventListener('change', () => { if (!dragVal) render(); }));
    // 아래 문항 번호 바에서 이 문항을 고르면 (숨은 입력칸에 focus) 칸에 테두리
    zones.forEach(z => {
      z.inp.addEventListener('focus', () => z.el.classList.add('hd-cur'));
      z.inp.addEventListener('blur', () => z.el.classList.remove('hd-cur'));
    });
    render();
    return true;
  }

  function init(root) {
    let n = 0;
    findBoxes(root).forEach(b => { if (setup(b)) n++; });
    return n;
  }

  window.ReadingHeadings = { init: init, _find: findBoxes, _inputs: answerInputs, _items: readItems };
})();
