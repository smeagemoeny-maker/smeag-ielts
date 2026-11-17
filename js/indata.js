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
  body.innerHTML = '<tr><td colspan="7">Loading...</td></tr>';
  window._sessions = [];
  updateSelected();
  panel.style.display = 'block';
  panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
  try {
    const d = await postForm('get_answer_dates.php', { id: s.id, ename: s.ename });
    if (!d.sessions || !d.sessions.length) {
      body.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#888">No test history found.</td></tr>';
      return;
    }
    body.innerHTML = d.sessions.map((r, i) =>
      '<tr><td style="text-align:center"><input type="checkbox" class="history-pick" value="' + i + '" onchange="updateSelected()"></td>'
      + '<td>' + esc(r.date) + '</td><td><b>' + esc(r.test_set) + '</b></td><td>' + esc(r.test_type) + '</td><td>' + esc(r.subjects) + '</td>'
      + '<td style="white-space:nowrap">' + (r.leaves || []).map(l => esc(l.subject) + ': ' + leaveText(l.count) + ' / ' + leaveText(l.fs)).join('<br>') + '</td>'
      + '<td style="text-align:center"><button class="mini-btn check" onclick="checkSession(' + i + ')">Check</button>'
      + '<button class="mini-btn pdf" onclick="sessionPdf(' + i + ')">PDF</button>'
      + '<button class="mini-btn del" onclick="deleteSession(' + i + ')">Delete</button></td></tr>').join('');
    window._sessions = d.sessions;
  } catch (e) {
    body.innerHTML = '<tr><td colspan="7" style="color:#d00">Failed to load history.</td></tr>';
  }
}
// ---- 여러 기록 한꺼번에 삭제 (머리 줄 체크박스 = 전체 선택) ----
function pickedSessions() {
  return [...document.querySelectorAll('#historyBody .history-pick:checked')].map(c => window._sessions[+c.value]);
}
function toggleAllHistory(on) {
  document.querySelectorAll('#historyBody .history-pick').forEach(c => { c.checked = on; });
  updateSelected();
}
function updateSelected() {
  const all = document.querySelectorAll('#historyBody .history-pick');
  const n = pickedSessions().length;
  const head = document.getElementById('selectAllHistory');
  head.checked = all.length > 0 && n === all.length;
  head.indeterminate = n > 0 && n < all.length;
  head.disabled = !all.length;
  const btn = document.getElementById('selectAllBtn');   // 위 "Select all" 버튼 — 다 고르면 "Clear selection"
  btn.disabled = !all.length;
  btn.textContent = head.checked ? 'Clear selection' : 'Select all';
  document.getElementById('deleteSelectedBtn').disabled = !n;
  document.getElementById('selectedCount').textContent = n ? n + ' selected' : '';
}
async function deleteSelected() {
  const picked = pickedSessions();
  if (!picked.length) return;
  const all = picked.length === window._sessions.length;
  if (!confirm('Delete ' + (all ? 'ALL ' : '') + picked.length + ' test record' + (picked.length === 1 ? '' : 's')
    + ' for ' + currentCheckUser.ename + '?\n\nThe student\'s answers for these tests will be removed permanently.')) return;
  try {
    const d = await postForm('delete_attempts.php', { id: currentCheckUser.id, ids: picked.flatMap(s => s.ids).join(',') });
    if (!d.success) throw new Error(d.error || 'failed');
    openCheckAnswerPanel(currentCheckUser);
  } catch (e) {
    alert('Could not delete the selected records.');
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
async function deleteSession(i) {
  const s = window._sessions[i];
  if (!confirm('Delete this test record?\n\n' + s.date + ' · ' + s.test_set + ' · ' + s.subjects
    + '\n\nThe student\'s answers for this test will be removed permanently.')) return;
  try {
    const d = await postForm('delete_attempts.php', { id: currentCheckUser.id, ids: s.ids.join(',') });
    if (!d.success) throw new Error(d.error || 'failed');
    openCheckAnswerPanel(currentCheckUser);
  } catch (e) {
    alert('Could not delete this record.');
  }
}
function sessionPdf(i) {
  const s = window._sessions[i];
  window.open('pdf.php?' + new URLSearchParams({ id: currentCheckUser.id, course: s.test_type, date: s.date.slice(0, 10), test_set: s.test_set }), '_blank');
}
async function checkSession(i) {
  const s = window._sessions[i];
  const box = document.getElementById('detailResults');
  box.innerHTML = '<p>Loading...</p>';
  const d = await postForm('get_answer_detail.php', { id: currentCheckUser.id, ids: s.ids.join(',') });
  let html = '<div class="detail-wrap"><h4>Detailed Results for ' + esc(s.date) + '</h4>';
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
