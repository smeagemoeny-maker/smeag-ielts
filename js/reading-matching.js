/*
 * IELTS Reading — 상자 속 보기(A, B, C …)에서 골라 쓰는 문제를 끌어다 놓기로 (컴퓨터 시험 CD IELTS 처럼)
 *   예: "Choose one phrase from the list in the box (A-F)", 문장 끝 맞추기 (sentence endings),
 *       사람·특징 맞추기 (list of people A-E), 상자 속 낱말(A-K)로 요약·표 채우기
 *       글자 없는 낱말 상자 ("Choose your answers from the box" → 값 = 낱말), 로마 숫자 목록 (i, ii … → 값 = 번호)
 *   - 문제 쪽(오른쪽 반)에서 A, B, C … 로 나뉘는 보기 상자를 찾아 보기마다 끌 수 있는 카드로 바꿈
 *     (상자 없이 문항 사이에 섞인 보기, 두 칸으로 나뉜 보기, 라벨 하나 빠진 보기도 처리. 라디오 객관식 · 한 문항짜리는 그대로)
 *   - 그 문제(“Questions 7-9” 범위)의 빈칸은 카드를 놓는 칸으로. 놓으면 원래 입력칸에 글자(A, B …)가 들어가 그대로 채점·자동 저장됨
 *   - 안내문에 "more than once" 가 있으면 같은 보기를 여러 칸에 쓸 수 있음 (목록에 남음). 아니면 한 보기는 한 칸에만
 *   - 칸에서 목록으로 다시 끌거나 × 를 누르면 돌아옴. 칸끼리 옮기기·맞바꾸기, "보기 클릭 → 칸 클릭" 으로도 놓기
 * 사용: ReadingMatching.init(root) — ReadingHeadings.init 다음, ListeningParts.init 보다 먼저
 */
(function () {
  const LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
  const isText = el => el.tagName === 'INPUT' && (!el.type || el.type === 'text');
  const clean = s => s.replace(/\s+/g, ' ').trim();
  const qn = el => { const m = /^q(\d+)$/.exec(el.name || ''); return m ? +m[1] : 0; };
  // "Questions 7-9" / "boxes 10–13" / "Questions 14" / "box 14"
  //   ("in the Blue Box 9 …" 같은 문항 글과 헷갈리지 않게 box 는 "boxes 10–13" 꼴만)
  const RANGE = /\b(?:questions?\s*(\d{1,2})(?:\s*(?:[-–—]|to|and)\s*(\d{1,2}))?|boxes\s*(\d{1,2})\s*(?:[-–—]|to|and)\s*(\d{1,2}))/gi;

  // 보기 라벨: "A", "A.", "(A)", "A)"
  function label(s) {
    const m = /^\(?([A-Z])[.)]?$/.exec(clean(s));
    return m ? m[1] : '';
  }

  // 노드 안 글을 줄(<br>·블록·표 줄)로 나눔. 표 칸은 같은 줄에 띄어 씀 (cells: 표 칸마다 따로 — 낱말 표)
  function lines(node, cells) {
    const out = [];
    let cur = '';
    (function walk(n) {
      n.childNodes.forEach(c => {
        if (c.nodeType === 3) { cur += c.nodeValue; return; }
        if (c.nodeType !== 1) return;
        if (!cells && (c.tagName === 'TD' || c.tagName === 'TH')) { cur += ' '; walk(c); cur += ' '; return; }
        const block = /^(BR|P|DIV|LI|TR|TD|TH|TABLE|UL|OL|CENTER|H\d)$/.test(c.tagName);
        if (block) { out.push(cur); cur = ''; }
        walk(c);
        if (block) { out.push(cur); cur = ''; }
      });
    })(node);
    out.push(cur);
    return out.map(clean).filter(Boolean);
  }

  // 1) 굵은 글씨 라벨 (<b>A</b> …, 또는 <b>D junk food …</b>) — 글 순서가 아니라 글자로 모음 (두 칸짜리 표: A D / B E …)
  //    보기 글 = 라벨 뒤부터 (글 순서로) 다음 라벨 앞까지
  function parseBold(box) {
    const seen = new Map();
    box.querySelectorAll('b,strong').forEach(b => {
      const m = /^\(?([A-Z])(?:[.)]?$|[.)]?\s+(.+)$)/.exec(clean(b.textContent));
      if (m && !seen.has(m[1])) seen.set(m[1], { b: b, value: m[1], rest: m[2] || '' });
    });
    // 굵은 글 안에 글이 붙은 라벨은 앞뒤 글자 라벨이 있을 때만 (제목 속 "I think" 같은 것과 헷갈리지 않게)
    seen.forEach((l, k) => {
      const i = LETTERS.indexOf(k);
      if (l.rest && !(seen.has(LETTERS[i - 1]) && !seen.get(LETTERS[i - 1]).rest) && !(seen.has(LETTERS[i + 1]) && !seen.get(LETTERS[i + 1]).rest)) seen.delete(k);
    });
    if (!seen.has('A')) return [];
    // A 부터 이어지는 글자만 (빠진 글자는 하나까지 — ACADEMIC10 "I" 라벨 빠짐)
    const labs = [];
    for (let i = 0, miss = 0; i < LETTERS.length && miss < 2; i++) {
      if (seen.has(LETTERS[i])) { labs.push(seen.get(LETTERS[i])); miss = 0; } else miss++;
    }
    if (labs.length < 3) return [];
    const order = labs.slice().sort((a, b) => a.b.compareDocumentPosition(b.b) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1);
    order.forEach((l, i) => {
      const r = document.createRange();
      r.setStartAfter(l.b);
      if (order[i + 1]) r.setEndBefore(order[i + 1].b); else r.setEnd(box, box.childNodes.length);
      l.range = r;
      l.text = clean(l.rest + ' ' + r.toString());
    });
    const items = [];
    labs.forEach((l, i) => {
      items.push({ value: l.value, text: l.text });
      // 다음 글자가 빠졌으면 (H 다음 J) 이 보기의 두 번째 줄이 빠진 글자의 보기
      const next = labs[i + 1];
      if (next && LETTERS.indexOf(next.value) === LETTERS.indexOf(l.value) + 2) {
        const ls = lines(l.range.cloneContents());
        if (ls.length === 2) {
          items[items.length - 1].text = clean(l.rest + ' ' + ls[0]);
          items.push({ value: LETTERS[LETTERS.indexOf(l.value) + 1], text: ls[1] });
        }
      }
    });
    return items;
  }

  // 2) 굵은 라벨이 없으면 줄 첫머리의 A, B, C … 로
  function parseLines(box) {
    const items = [];
    lines(box).forEach(l => {
      const m = /^\(?([A-Z])[.)]?\s+(.+)$/.exec(l);
      if (m && m[1] === LETTERS[items.length]) items.push({ value: m[1], text: m[2] });
      else if (items.length && !/^\(?[A-Z][.)]?$/.test(l)) items[items.length - 1].text += ' ' + l;   // 보기가 두 줄에 걸침
    });
    return items.length >= 3 ? items : [];
  }

  function parse(box) {
    const bold = parseBold(box);
    const items = (bold.length ? bold : parseLines(box)).map(it => ({
      value: it.value,
      // 문제지 오타 정리: "D D Restrictions …" (라벨 되풀이), 줄 끝 글머리표 "•"
      //   (A · I 는 글 첫 낱말 "A clean …" "I think …" 일 수 있어 그대로)
      text: (/^[AI]$/.test(it.value) ? it.text : it.text.replace(new RegExp('^' + it.value + '\\s+(?=[A-Z])'), '')).replace(/\s*[•·]\s*$/, '')
    })).filter(it => it.text);
    return items.length >= 3 && items[0].value === 'A' ? items : [];
  }

  // 상자 첫머리의 짧은 제목 ("List of periods", "List of Words") — 새 목록 위에 그대로 보여 줌
  function titleOf(box, items) {
    const first = lines(box)[0] || '';
    if (items[0] && items[0].value === 'A' && /^\(?A[.)]?\s/.test(first)) return '';
    return first.length <= 60 && /^(list|words?|phrases?|names?|people|researchers?|scientists?)\b/i.test(first) ? first : '';
  }

  // 글자 없는 낱말 상자 ("Complete the summary using words from the box"): 줄마다 짧은 낱말·구 → 보기 (값 = 그 낱말)
  const WORD_TITLE = /^(list of\b|words?\b|phrases?\b)/i;
  const ROMANS = ['i', 'ii', 'iii', 'iv', 'v', 'vi', 'vii', 'viii', 'ix', 'x', 'xi', 'xii', 'xiii', 'xiv', 'xv'];
  function parseWords(box) {
    let ls = lines(box, true);
    if (ls.length && WORD_TITLE.test(ls[0])) ls = ls.slice(1);
    // 로마 숫자 목록 (GENERAL3 15-21 "Write the correct number, i–xi") → 값 = 번호
    const rom = ls.map(l => /^([ivx]+)[.)]?\s+(.+)$/.exec(l));
    if (ls.length >= 3 && rom.every((m, i) => m && m[1] === ROMANS[i])) return rom.map(m => ({ value: m[1], text: m[2], roman: true }));
    if (ls.length < 4 || ls.some(l => l.split(' ').length > 5 || /^(example|answer)\b/i.test(l) || /\d{1,2}\s*$/.test(l))) return [];
    return ls.map(l => ({ value: l, text: l }));
  }

  // 문항과 한 덩어리에 섞여 있는 보기 (<b>A</b> … <b>B</b> … 가 입력칸과 같은 div 안, 또는 긴 안내문 뒤) 를
  // 따로 div 로 감싸서 상자로 만듦 (ACADEMIC14 28-33, ACADEMIC4 25-28 …)
  const BLOCK = /^(DIV|P|TABLE|H\d|UL|OL|CENTER|SECTION)$/;
  function wrapLoose(half) {
    [...half.querySelectorAll('*')].forEach(p => {
      if (p.closest('.hd-list,.mt-loose')) return;
      let run = [];
      for (const b of p.children) {
        if (!/^(B|STRONG)$/.test(b.tagName)) continue;
        const i = LETTERS.indexOf(label(b.textContent));
        if (i < 0 || !label(b.textContent)) continue;
        const prev = run.length ? LETTERS.indexOf(label(run[run.length - 1].textContent)) : -1;
        if (i === 0 && run.length < 3) run = [b];
        else if (run.length && i > prev && i <= prev + 2) run.push(b);
      }
      if (run.length < 3) return;
      const r = document.createRange();
      r.setStartBefore(run[0]); r.setEndAfter(run[run.length - 1]);
      if (r.cloneContents().querySelector('input,select,textarea')) return;
      const before = document.createRange();
      before.setStart(p, 0); before.setEndBefore(run[0]);
      if (!p.querySelector('input,select,textarea') && clean(before.toString()).length <= 80) return;   // 이미 따로 된 상자
      // 마지막 보기의 끝: 다음 <br> (포함) 또는 블록 · 입력칸 · 문항 번호 앞
      let end = run[run.length - 1];
      for (let n = end.nextSibling; n; n = n.nextSibling) {
        if (n.nodeType === 1) {
          if (BLOCK.test(n.tagName) || n.querySelector('input,select,textarea') || /^(INPUT|SELECT|TEXTAREA|LABEL)$/.test(n.tagName)
            || (/^(B|STRONG)$/.test(n.tagName) && /^\d/.test(clean(n.textContent)))) break;
          end = n;
          if (n.tagName === 'BR') break;
        } else end = n;
      }
      const w = document.createElement('div');
      w.className = 'mt-loose';
      p.insertBefore(w, run[0]);
      let n = run[0];
      while (n) { const next = n.nextSibling; w.appendChild(n); if (n === end) break; n = next; }
    });
  }

  // 문제 쪽 반 (지문 쪽 문단 A–I 와 헷갈리지 않게)
  function questionHalves(root) {
    return [...root.querySelectorAll('.page')].map(page => {
      const halves = [...page.querySelectorAll('[class*="md:w-1/2"]')].filter(h => h.closest('.page') === page);
      return halves[1] || null;
    }).filter(Boolean);
  }

  // 상자가 속한 문제의 문항 범위: 상자 앞 글에서 마지막 "Questions 7-9" / "boxes 10–13"
  function rangeOf(half, box) {
    const r = document.createRange();
    r.setStart(half, 0);
    r.setEndBefore(box);
    const m = [...r.toString().matchAll(RANGE)].pop();
    if (!m) return null;
    const a = +(m[1] || m[3]);
    return [a, +(m[2] || m[4] || a)];
  }

  // 그 범위의 안내문 (마지막 "Questions" 제목부터 상자까지) — "more than once" 찾기
  function instructions(half, box) {
    const r = document.createRange();
    r.setStart(half, 0);
    r.setEndBefore(box);
    const t = clean(r.toString());
    const m = [...t.matchAll(/\bquestions?\s*\d{1,2}/gi)].pop();
    return m ? t.slice(m.index) : t.slice(-600);
  }

  // 상자가 속한 문제의 입력칸 (범위 안 문항이 모두 글 입력칸이고 2개 이상일 때만 — 객관식 라디오 · 한 문항짜리 객관식은 그대로)
  function inputsOf(half, box) {
    const range = rangeOf(half, box);
    if (!range) return null;
    const all = [...half.querySelectorAll('input,select,textarea')].filter(el => { const n = qn(el); return n >= range[0] && n <= range[1]; });
    const inputs = all.filter(el => isText(el) && !el.classList.contains('hd-input') && !el.classList.contains('mt-input'));
    return inputs.length >= 2 && inputs.length === all.length ? inputs : null;
  }

  const minimal = cands => cands.filter(el => !cands.some(o => o !== el && el.contains(o)));
  const noInputs = el => !el.querySelector('input,select,textarea') && !el.closest('.hd-list,.mt-list');
  const SEL = 'div,td,table,ul,ol,center,p';

  function findSets(root) {
    const sets = [];
    questionHalves(root).forEach(half => {
      wrapLoose(half);
      // 1) 글자 보기 상자 (A, B, C …)
      // 보기가 두 칸으로 나뉜 상자 (GENERAL3: A–D | E–H) 는 둘을 감싼 상자까지 넓힘
      const grow = box => {
        for (let p = box.parentElement; p && p !== half && noInputs(p) && p.matches(SEL); p = p.parentElement) {
          if (parse(p).length > parse(box).length) box = p;
        }
        return box;
      };
      const boxes = [...new Set(minimal([...half.querySelectorAll(SEL)].filter(el => noInputs(el) && parse(el).length)).map(grow))];
      boxes.forEach(box => {
        const inputs = inputsOf(half, box);
        if (!inputs) return;
        const items = parse(box);
        sets.push({ box: box, items: items, title: titleOf(box, items), letters: true, inputs: inputs, reuse: /more than once/i.test(instructions(half, box)) });
      });
      // 2) 글자 없는 낱말 상자 — 안내문이 "from the box / list of words" 일 때만
      const taken = el => sets.some(s => s.box.contains(el) || el.contains(s.box));
      const wc = [...half.querySelectorAll(SEL)].filter(el => noInputs(el) && !taken(el) && parseWords(el).length);
      const words = wc.filter(el => !wc.some(o => o !== el && o.contains(el)));   // 가장 큰 상자 (여러 칸으로 나뉜 낱말 표를 한 목록으로)
      words.forEach(box => {
        const ins = instructions(half, box);
        if (!/from the (box|list)|in the box|list of words|words (and phrases )?from the box|choose your answers from/i.test(ins)) return;
        const inputs = inputsOf(half, box);
        if (!inputs) return;
        let title = '';
        const ls = lines(box);
        if (ls.length && WORD_TITLE.test(ls[0])) title = ls[0];
        const items = parseWords(box);
        sets.push({ box: box, items: items, title: title, letters: !!items[0].roman, inputs: inputs, reuse: /more than once/i.test(ins) });
      });
    });
    return sets;
  }

  function esc(s) { return String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c])); }

  let styled = false;
  function addStyle() {
    if (styled) return; styled = true;
    const st = document.createElement('style');
    st.textContent = ''
      + '.mt-list{border:1px solid #000;padding:10px 12px;margin:8px 0 14px;max-width:700px;background:#fff}'
      + '.mt-title{text-align:center;font-weight:700;margin-bottom:8px}'
      + '.mt-items{display:flex;flex-direction:column;gap:5px;min-height:30px}'
      + '.mt-words .mt-items{flex-direction:row;flex-wrap:wrap}'
      + '.mt-chip{display:flex;gap:8px;align-items:baseline;padding:5px 10px;border:1px solid #8a8a8a;border-radius:3px;background:#fff;cursor:grab;user-select:none;line-height:1.35;color:#000}'
      + '.mt-chip:hover{border-color:#1a5bb8;background:#f3f7fd}'
      + '.mt-chip b{min-width:16px;font-weight:700}'
      + '.mt-chip.mt-picked{outline:2px solid #1a5bb8;background:#e7f0fc}'
      + '.mt-chip.mt-dragging{opacity:.45}'
      + '.mt-list.mt-over{background:#f3f7fd}'
      // 원래 입력칸: 값만 들고 보이지 않게 (제자리에 크기 0)
      + '.legacy-content input.mt-input{display:inline-block!important;width:0!important;min-width:0!important;height:0!important;padding:0!important;margin:0!important;border:0!important;opacity:0!important;pointer-events:none;vertical-align:middle}'
      + '.mt-zone{display:inline-flex;align-items:center;gap:6px;vertical-align:middle;min-width:150px;max-width:100%;min-height:30px;margin:3px 6px;padding:2px 6px;border:1px dashed #8a8a8a;border-radius:3px;background:#fff;font-weight:700;color:#555;cursor:pointer;text-align:left}'
      + '.mt-zone.mt-empty{justify-content:center}'
      + '.mt-zone.mt-over,.mt-zone.mt-target{border:2px solid #1a5bb8;background:#eef4fd}'
      + '.mt-zone.mt-cur{border-color:#1a5bb8}'
      + '.mt-zone .mt-chip{flex:1;border:0;padding:3px 4px;background:none;font-weight:400}'
      + '.mt-zone .mt-x{border:0;background:none;color:#888;font-size:16px;line-height:1;cursor:pointer;padding:0 4px}'
      + '.mt-zone .mt-x:hover{color:#c00}';
    document.head.appendChild(st);
  }

  function setup(s) {
    const { box, items, inputs, reuse, letters, title } = s;
    addStyle();
    const key = v => clean(String(v || '')).toLowerCase();
    const count = new Map();   // 같은 낱말이 상자에 두 번 있으면 (ACADEMIC17 "estimate") 두 칸까지
    items.forEach(it => count.set(key(it.value), (count.get(key(it.value)) || 0) + 1));

    const list = document.createElement('div');
    list.className = 'mt-list' + (letters ? '' : ' mt-words');
    list.innerHTML = (title ? '<div class="mt-title">' + esc(title) + '</div>' : '') + '<div class="mt-items"></div>';
    box.after(list);
    box.style.display = 'none';
    const itemsEl = list.querySelector('.mt-items');

    const zones = inputs.map(inp => {
      const z = document.createElement('span');
      z.className = 'mt-zone mt-empty';
      z.dataset.q = qn(inp);
      inp.after(z);
      inp.classList.add('mt-input');
      inp.readOnly = true;
      inp.tabIndex = -1;
      return { n: qn(inp), inp: inp, el: z };
    });

    const chip = i => '<span class="mt-chip" draggable="true" data-i="' + i + '">'
      + (letters ? '<b>' + esc(items[i].value) + '</b>' : '') + '<span>' + esc(items[i].text) + '</span></span>';
    const val = z => key(z.inp.value);
    const itemOf = k => items.findIndex(it => key(it.value) === k);
    const usedBy = (k, except) => zones.filter(o => o !== except && val(o) === k).length;

    function render() {
      const left = new Map();
      zones.forEach(z => { const k = val(z); if (count.has(k)) left.set(k, (left.get(k) || 0) + 1); });
      itemsEl.innerHTML = items.map((it, i) => {
        const k = key(it.value);
        if (!reuse && left.get(k) > 0) { left.set(k, left.get(k) - 1); return ''; }   // 칸에 놓인 만큼 목록에서 뺌
        return chip(i);
      }).join('');
      zones.forEach(z => {
        const i = itemOf(val(z));
        z.el.classList.toggle('mt-empty', i < 0);
        z.el.innerHTML = i >= 0 ? chip(i) + '<button type="button" class="mt-x" title="Remove">&times;</button>' : String(z.n);
      });
    }
    function set(z, value) {
      if (val(z) === key(value)) return;
      z.inp.value = value;
      z.inp.dispatchEvent(new Event('input', { bubbles: true }));
      z.inp.dispatchEvent(new Event('change', { bubbles: true }));
    }
    function put(z, i) {
      const k = key(items[i].value);
      if (!reuse && usedBy(k, z) >= count.get(k)) {   // 한 보기는 한 칸에만: 먼저 놓인 칸에서 뺌
        const other = zones.find(o => o !== z && val(o) === k);
        if (other) set(other, '');
      }
      set(z, items[i].value);
      render();
    }

    // ── 마우스로 끌기 ──
    let dragI = null, dragFrom = null;
    const zoneOf = el => { const z = el && el.closest && el.closest('.mt-zone'); return z ? zones.find(o => o.el === z) : null; };
    const mine = c => list.contains(c) || zones.some(z => z.el.contains(c));
    document.addEventListener('dragstart', e => {
      const c = e.target.closest && e.target.closest('.mt-chip');
      if (!c || !mine(c)) return;
      dragI = +c.dataset.i; dragFrom = zoneOf(c);
      e.dataTransfer.effectAllowed = 'move';
      try { e.dataTransfer.setData('text/plain', items[dragI].value); } catch (er) {}
      c.classList.add('mt-dragging');
    });
    document.addEventListener('dragend', () => {
      if (dragI === null) return;
      dragI = dragFrom = null;
      document.querySelectorAll('.mt-over').forEach(x => x.classList.remove('mt-over'));
      render();
    });
    zones.forEach(z => {
      z.el.addEventListener('dragover', e => { if (dragI !== null) { e.preventDefault(); z.el.classList.add('mt-over'); } });
      z.el.addEventListener('dragleave', () => z.el.classList.remove('mt-over'));
      z.el.addEventListener('drop', e => {
        e.preventDefault(); z.el.classList.remove('mt-over');
        if (dragI === null) return;
        const i = dragI, prev = itemOf(val(z));
        if (dragFrom === z) return;
        if (dragFrom) set(dragFrom, prev >= 0 ? items[prev].value : '');   // 칸끼리 옮기기 · 맞바꾸기
        put(z, i);
      });
    });
    list.addEventListener('dragover', e => { if (dragI !== null && dragFrom) { e.preventDefault(); list.classList.add('mt-over'); } });
    list.addEventListener('dragleave', () => list.classList.remove('mt-over'));
    list.addEventListener('drop', e => { e.preventDefault(); list.classList.remove('mt-over'); if (dragFrom) { set(dragFrom, ''); render(); } });

    // ── 클릭으로 놓기 (보기 클릭 → 칸 클릭) ──
    let picked = null;
    function pick(i) {
      picked = i;
      list.querySelectorAll('.mt-chip').forEach(c => c.classList.toggle('mt-picked', +c.dataset.i === i));
      zones.forEach(z => z.el.classList.toggle('mt-target', i !== null));
    }
    list.addEventListener('click', e => {
      const c = e.target.closest('.mt-chip');
      pick(c && picked !== +c.dataset.i ? +c.dataset.i : null);
    });
    zones.forEach(z => z.el.addEventListener('click', e => {
      if (e.target.closest('.mt-x')) { set(z, ''); render(); pick(null); return; }
      if (picked !== null) { put(z, picked); pick(null); }
      z.inp.focus({ preventScroll: true });   // 아래 문항 번호 바의 현재 문항 표시
    }));
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && picked !== null) pick(null); });

    // 자동 저장에서 되살린 값 등 바깥에서 입력칸이 바뀌면 다시 그림
    inputs.forEach(inp => inp.addEventListener('change', () => { if (dragI === null) render(); }));
    zones.forEach(z => {
      z.inp.addEventListener('focus', () => z.el.classList.add('mt-cur'));
      z.inp.addEventListener('blur', () => z.el.classList.remove('mt-cur'));
    });
    // 문서 순서로 첫 빈칸 위에 안내
    addHint(inputs.slice().sort((a, b) => a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1)[0]);
    render();
  }

  // 끌어다 놓는 문제 첫 문항 바로 위에 안내 한 줄 ("Move your answer into the gap.") — js/reading-headings.js 와 같음
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

  function init(root) {
    const sets = findSets(root);
    sets.forEach(setup);
    return sets.length;
  }

  window.ReadingMatching = { init: init, _find: findSets, _items: parse };
})();
