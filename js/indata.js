// indata.php 화면 동작 (원본 함수 이름 그대로)
function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

async function postForm(url, data) {
  const res = await fetch(url, { method: 'POST', body: new URLSearchParams(data) });
  if (!res.ok) throw new Error('HTTP ' + res.status);
  return res.json();
}

function today() {
  const d = new Date();
  return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

// ---- Enter Score/Comment ----
function openScoreModal(s) {
  document.getElementById('m_id').value = s.id;
  document.getElementById('m_ename').value = s.ename;
  document.getElementById('m_course').value = s.course;
  document.getElementById('m_date').value = today();
  document.querySelectorAll('#scoreModal input[type=number], #scoreModal textarea').forEach(el => el.value = '');
  document.getElementById('scoreModal').classList.add('show');
}
function closeScoreModal() {
  document.getElementById('scoreModal').classList.remove('show');
}
document.getElementById('scoreModal').addEventListener('click', e => { if (e.target.id === 'scoreModal') closeScoreModal(); });

// ---- INFO ----
async function openInfoPanel(s) {
  const modal = document.getElementById('infoModal');
  const body = document.getElementById('infoBody');
  body.textContent = 'Loading...';
  modal.classList.add('show');
  try {
    const res = await fetch('student_info_api.php?' + new URLSearchParams({ id: s.id, ename: s.ename, course: s.course }));
    const d = await res.json();
    if (!d.success) throw new Error(d.message || 'error');
    const u = d.student;
    const rows = [
      ['ID', u.login_id], ['English Name', u.ename], ['Global Name', u.gname], ['Course', u.course],
      ['Campus', u.campus], ['Gender', u.gender], ['Birth Date', u.birth], ['Phone', u.phone],
      ['Tests Taken', d.attempts], ['Joined', (u.created_at || '').slice(0, 10)],
    ];
    let html = '<div class="info-grid">' + rows.map(r => '<div>' + esc(r[0]) + '</div><div>' + esc(r[1]) + '</div>').join('') + '</div>';
    if (d.latest_score) {
      const sc = d.latest_score;
      html += '<h3 style="color:#6c63ff;margin:20px 0 8px">Latest Score (' + esc(sc.test_type) + ', ' + esc(sc.test_date) + ')</h3>'
        + '<div class="info-grid">'
        + [['Listening', sc.listening_score], ['Reading', sc.reading_score], ['Speaking', sc.speaking_score], ['Writing', sc.writing_score], ['Comment', sc.sa_teacher_comment]]
          .filter(r => r[1] !== null && r[1] !== '').map(r => '<div>' + esc(r[0]) + '</div><div>' + esc(r[1]) + '</div>').join('')
        + '</div>';
    }
    body.innerHTML = html;
  } catch (e) {
    body.textContent = 'Failed to load student info.';
  }
}
document.getElementById('infoModal').addEventListener('click', e => { if (e.target.id === 'infoModal') e.target.classList.remove('show'); });

// ---- PDF ----
function showPdfPanel(s) {
  document.getElementById('pdfUserId').value = s.id;
  const sel = document.getElementById('pdfCourse');
  sel.value = s.course;
  document.getElementById('pdfDownloadPanel').style.display = 'block';
  loadPdfDates();
  document.getElementById('pdfDownloadPanel').scrollIntoView({ behavior: 'smooth', block: 'center' });
}
async function loadPdfDates() {
  const sel = document.getElementById('pdfDate');
  const none = document.getElementById('pdfNoDates');
  sel.innerHTML = '';
  try {
    const d = await postForm('get_dates.php', { id: document.getElementById('pdfUserId').value, course: document.getElementById('pdfCourse').value });
    (d.dates || []).forEach(dt => sel.add(new Option(dt, dt)));
    none.style.display = (d.dates || []).length ? 'none' : 'block';
  } catch (e) {
    none.style.display = 'block';
  }
}
function downloadPdf() {
  const date = document.getElementById('pdfDate').value;
  if (!date) { alert('No test dates found.'); return; }
  window.open('pdf.php?' + new URLSearchParams({ id: document.getElementById('pdfUserId').value, course: document.getElementById('pdfCourse').value, date }), '_blank');
}

// ---- Check Answer ----
let currentCheckUser = null;
async function openCheckAnswerPanel(s) {
  currentCheckUser = s;
  const panel = document.getElementById('answerCheckPanel');
  document.getElementById('checkTitle').textContent = 'IELTS Test History - ' + s.ename;
  document.getElementById('detailResults').innerHTML = '';
  const body = document.getElementById('historyBody');
  body.innerHTML = '<tr><td colspan="6">Loading...</td></tr>';
  window._sessions = [];
  panel.style.display = 'block';
  panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
  try {
    const d = await postForm('get_answer_dates.php', { id: s.id, ename: s.ename });
    if (!d.sessions || !d.sessions.length) {
      body.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#888">No test history found.</td></tr>';
      return;
    }
    body.innerHTML = d.sessions.map((r, i) =>
      '<tr><td>' + esc(r.date) + '</td><td><b>' + esc(r.test_set) + '</b></td><td>' + esc(r.test_type) + '</td><td>' + esc(r.subjects) + '</td>'
      + '<td style="white-space:nowrap">' + (r.leaves || []).map(l => esc(l.subject) + ': ' + leaveText(l.count) + ' / ' + leaveText(l.fs)).join('<br>') + '</td>'
      + '<td style="text-align:center"><button class="mini-btn check" onclick="checkSession(' + i + ')">Check</button>'
      + '<button class="mini-btn pdf" onclick="sessionPdf(' + i + ')">PDF</button></td></tr>').join('');
    window._sessions = d.sessions;
  } catch (e) {
    body.innerHTML = '<tr><td colspan="6" style="color:#d00">Failed to load history.</td></tr>';
  }
}
// 시험 화면을 떠난 횟수 (null = 이 기록을 남기기 전 응시)
function leaveText(n) {
  if (n === null || n === undefined) return '<span style="color:#888">—</span>';
  return n > 0 ? '<b style="color:#b00020">' + n + (n === 1 ? ' time' : ' times') + '</b>' : '<span style="color:#15803d">0</span>';
}
function closeCheckPanel() {
  document.getElementById('answerCheckPanel').style.display = 'none';
}
// ---- Answer Check PDF: 바로 다운로드 (IELTS_Answer_Check_이름_날짜.pdf) ----
async function sessionPdf(i) {
  const s = window._sessions[i];
  if (!window.jspdf) { alert('The PDF library could not be loaded. Check the internet connection and reload the page.'); return; }
  try {
    const d = await postForm('get_answer_detail.php', { id: currentCheckUser.id, ids: s.ids.join(',') });
    if (!d.success) throw new Error('failed');
    const name = (d.student && d.student.ename) || currentCheckUser.ename;
    const file = 'IELTS_Answer_Check_' + String(name).replace(/[\\/:*?"<>|]/g, '') + '_' + String(s.date).replace(/[: ]/g, '_') + '.pdf';
    buildAnswerPdf(s, d, name).save(file);
  } catch (e) {
    alert('Could not create the PDF.');
  }
}

// 기본 PDF 글꼴(Helvetica)에 없는 글자 정리
function pdfText(s) {
  return String(s ?? '').replace(/\r\n?/g, '\n').replace(/[‘’]/g, "'").replace(/[“”]/g, '"')
    .replace(/[–—]/g, '-').replace(/…/g, '...').replace(/[^\n\x20-\x7E\xA0-\xFF]/g, '?');
}

function buildAnswerPdf(s, d, name) {
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF({ unit: 'mm', format: 'a4' });
  const W = 210, H = 297, M = 15, CW = W - 2 * M, BOTTOM = H - 22;
  const C = {
    purple: [108, 99, 255], text: [51, 51, 51], line: [221, 221, 240], card: [247, 248, 255], head: [243, 242, 255],
    ok: [26, 111, 216], ng: [224, 48, 48], gray: [150, 150, 150], box: [250, 250, 250], boxLine: [234, 234, 234],
  };
  const PT = 0.3528;   // 1pt = mm
  const type = s.test_type || 'IELTS';
  const course = type.replace(/^IELTS\s+/i, '') || type;
  let firstPage = true;

  function field(x, y, label, value) {
    doc.setFontSize(9.5); doc.setTextColor(...C.text);
    doc.setFont('helvetica', 'bold'); doc.text(label, x, y);
    const w = doc.getTextWidth(label + ' ');
    doc.setFont('helvetica', 'normal'); doc.text(pdfText(value), x + w, y);
  }
  // 모든 쪽 위: 제목 + 학생 정보 카드
  function newPage() {
    if (!firstPage) doc.addPage();
    firstPage = false;
    doc.setFont('helvetica', 'bold'); doc.setFontSize(20); doc.setTextColor(...C.purple);
    doc.text(pdfText(type + ' Answer Check'), W / 2, 22, { align: 'center' });
    doc.setFillColor(...C.card); doc.setDrawColor(...C.line); doc.setLineWidth(0.3);
    doc.roundedRect(M, 30, CW, 19, 2.5, 2.5, 'FD');
    field(M + 4.5, 37.5, 'Student Name:', name);
    field(M + 86, 37.5, 'Student ID:', (d.student && d.student.login_id) || '');
    field(M + 4.5, 44.5, 'Course:', course);
    field(M + 86, 44.5, 'Test Date:', s.date);
    return 58;
  }
  function sectionBar(y, title) {
    doc.setFillColor(...C.purple); doc.roundedRect(M, y, CW, 11, 1.5, 1.5, 'F');
    doc.setFont('helvetica', 'bold'); doc.setFontSize(14); doc.setTextColor(255, 255, 255);
    doc.text(title, M + 4, y + 7.6);
    return y + 16;
  }
  // 칸 안 가운데 정렬 (길면 글자 줄이고, 그래도 길면 2줄)
  function cell(t, x, w, top, h, o = {}) {
    t = pdfText(t);
    if (!t) return;
    let fs = o.size || 9;
    doc.setFont('helvetica', o.bold ? 'bold' : 'normal'); doc.setTextColor(...(o.color || C.text)); doc.setFontSize(fs);
    while (fs > 6.5 && doc.getTextWidth(t) > w - 2) doc.setFontSize(fs -= 0.5);
    const lines = doc.splitTextToSize(t, w - 2).slice(0, 2);
    const lh = fs * PT * 1.15;
    const y0 = top + h / 2 - (lines.length - 1) * lh / 2 + fs * PT * 0.35;
    lines.forEach((ln, k) => doc.text(ln, x + w / 2, y0 + k * lh, { align: 'center' }));
  }
  // 1–20 | 21–40 두 줄 표
  function answerTable(y, rows) {
    const cols = [7, 36, 31, 15, 7, 32, 37, 15];
    const xs = cols.reduce((a, w, k) => (a.push(k ? a[k - 1] + cols[k - 1] : M), a), []);
    const headH = 10, rowH = 7, half = Math.ceil(rows.length / 2);
    doc.setFillColor(...C.head); doc.rect(M, y, CW, headH, 'F');
    ['#', 'Student Answer', 'Correct Answer', 'Result', '#', 'Student Answer', 'Correct Answer', 'Result']
      .forEach((t, k) => cell(t, xs[k], cols[k], y, headH, { bold: true, color: C.purple, size: 9.5 }));
    for (let r = 0; r < half; r++) {
      const top = y + headH + r * rowH;
      [rows[r], rows[r + half]].forEach((row, side) => {
        if (!row) return;
        const k = side * 4;
        cell(row.no, xs[k], cols[k], top, rowH);
        cell(row.given, xs[k + 1], cols[k + 1], top, rowH);
        cell(row.correct, xs[k + 2], cols[k + 2], top, rowH);
        cell(row.ok ? 'O' : 'X', xs[k + 3], cols[k + 3], top, rowH, { bold: true, color: row.ok ? C.ok : C.ng, size: 10 });
      });
    }
    const bottom = y + headH + half * rowH;
    doc.setDrawColor(...C.line); doc.setLineWidth(0.25);
    doc.rect(M, y, CW, bottom - y);
    for (let r = 0; r <= half; r++) doc.line(M, y + headH + r * rowH, M + CW, y + headH + r * rowH);
    xs.slice(1).forEach(x => doc.line(x, y, x, bottom));
    return bottom + 8;
  }
  // Writing: Task 제목 · 학생 글 상자 · 빈 FEEDBACK 상자
  function writingTask(y, n, txt, wc) {
    doc.setFillColor(...C.purple); doc.rect(M, y, 1.2, 6.5, 'F');
    doc.setFont('helvetica', 'bold'); doc.setFontSize(12.5); doc.setTextColor(...C.purple);
    doc.text('Task ' + n + '. WORDS NUMBER : [ ' + wc + ' ] WORDS', M + 4.5, y + 5);
    y += 11;
    const fs = 9.5, lh = fs * PT * 1.75, pad = 5;
    doc.setFont('helvetica', 'normal'); doc.setFontSize(fs);
    const lines = pdfText(txt).split('\n').flatMap(p => p.trim() ? doc.splitTextToSize(p, CW - 2 * pad) : ['']);
    let idx = 0;
    do {
      const fit = Math.max(1, Math.floor((BOTTOM - y - 2 * pad) / lh));
      if (fit < 3 && idx < lines.length) { y = newPage(); continue; }
      const chunk = lines.slice(idx, idx + fit);
      idx += chunk.length;
      const boxH = chunk.length * lh + 2 * pad - 1;
      doc.setFillColor(...C.box); doc.setDrawColor(...C.boxLine); doc.setLineWidth(0.25);
      doc.roundedRect(M, y, CW, boxH, 1, 1, 'FD');
      doc.setFont('helvetica', 'normal'); doc.setFontSize(fs); doc.setTextColor(...C.text);
      chunk.forEach((ln, k) => doc.text(ln, M + pad, y + pad + fs * PT * 0.8 + k * lh));
      y += boxH + 6;
      if (idx < lines.length) y = newPage();
    } while (idx < lines.length);
    const fbH = 58;
    if (y + fbH > BOTTOM) y = newPage();
    doc.setDrawColor(...C.purple); doc.setLineWidth(0.5);
    doc.roundedRect(M, y, CW, fbH, 2.5, 2.5, 'S');
    doc.setFont('helvetica', 'bold'); doc.setFontSize(10.5); doc.setTextColor(...C.purple);
    doc.text('FEEDBACK', M + 1, y + 4.8);
    return y + fbH + 8;
  }

  const sections = d.sections || [];
  for (const sec of sections) {
    let y = sectionBar(newPage(), sec.name);
    if (sec.name === 'Writing') {
      y = writingTask(y, 1, sec.task1, sec.wc1 ?? 0);
      writingTask(newPage(), 2, sec.task2, sec.wc2 ?? 0);
    } else {
      answerTable(y, sec.rows || []);
    }
  }
  if (!sections.length) {
    const y = newPage();
    doc.setFont('helvetica', 'normal'); doc.setFontSize(11); doc.setTextColor(...C.gray);
    doc.text('No answers found for this test.', M, y + 4);
  }

  const n = doc.getNumberOfPages();
  for (let p = 1; p <= n; p++) {
    doc.setPage(p);
    doc.setFont('helvetica', 'normal'); doc.setFontSize(8.5); doc.setTextColor(...C.gray);
    doc.text('Page ' + p + ' of ' + n, W - M, H - 10, { align: 'right' });
  }
  return doc;
}
async function checkSession(i) {
  const s = window._sessions[i];
  const box = document.getElementById('detailResults');
  box.innerHTML = '<p>Loading...</p>';
  const d = await postForm('get_answer_detail.php', { id: currentCheckUser.id, ids: s.ids.join(',') });
  let html = '<div class="detail-wrap"><h4>Detailed Results for ' + esc(s.date)
    + ' <button type="button" class="mini-btn pdf" style="margin-left:10px;vertical-align:middle" onclick="sessionPdf(' + i + ')">Download PDF</button></h4>';
  for (const sec of d.sections || []) {
    html += '<div class="detail-section"><h5>' + esc(sec.name) + (sec.name !== 'Writing' ? ' — Score: ' + sec.score + ' / 40' : '')
      + ' — Left test screen: ' + leaveText(sec.leaves) + ' — Exited full screen: ' + leaveText(sec.fs) + '</h5>';
    if (sec.name === 'Writing') {
      html += '<table class="detail-table"><tr><th style="width:80px">Task</th><th>Student Answer</th></tr>'
        + '<tr><td>Task 1</td><td><pre>' + esc(sec.task1) + '</pre></td></tr>'
        + '<tr><td>Task 2</td><td><pre>' + esc(sec.task2) + '</pre></td></tr></table>';
    } else {
      html += '<table class="detail-table"><tr><th style="text-align:center">#</th><th>Your Answer</th><th>Correct Answer</th><th style="text-align:center">Result</th></tr>'
        + sec.rows.map(r => '<tr><td class="num">' + r.no + '</td><td>' + esc(r.given) + '</td><td><b>' + esc(r.correct) + '</b></td>'
          + '<td class="res ' + (r.ok ? 'result-correct' : 'result-incorrect') + '">' + (r.ok ? 'Correct' : 'Incorrect') + '</td></tr>').join('')
        + '</table>';
    }
    html += '</div>';
  }
  box.innerHTML = html + '</div>';
  box.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
