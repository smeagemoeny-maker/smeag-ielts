<?php
require_once __DIR__ . '/../includes/layout.php';
$me = require_admin();
fix_toefl_spelling();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        q('DELETE FROM schedules WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
        $_SESSION['flash'] = ['success', 'Schedule deleted.'];
    } elseif (in_array($action, ['add', 'edit'], true)) {
        $data = [
            (int)($_POST['student_id'] ?? 0), $_POST['test_date'] ?? '', $_POST['test_time'] ?? '',
            trim($_POST['test_subject'] ?? ''), $_POST['test_type'] ?? '', trim($_POST['location'] ?? ''), trim($_POST['notes'] ?? ''),
        ];
        $ok = $data[0] && q("SELECT 1 FROM users WHERE id = ? AND user_type = 'student'", [$data[0]])->fetch()
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data[1]) && preg_match('/^\d{2}:\d{2}/', $data[2]);
        if (!$ok) {
            $_SESSION['flash'] = ['error', 'Please select a student, date and time.'];
        } elseif ($action === 'add') {
            q('INSERT INTO schedules (student_id, test_date, test_time, test_subject, test_type, location, notes, created_at) VALUES (?,?,?,?,?,?,?,?)', [...$data, now()]);
            $_SESSION['flash'] = ['success', 'Schedule added.'];
        } else {
            q('UPDATE schedules SET student_id=?, test_date=?, test_time=?, test_subject=?, test_type=?, location=?, notes=? WHERE id = ?',
                [...$data, (int)($_POST['id'] ?? 0)]);
            $_SESSION['flash'] = ['success', 'Schedule updated.'];
        }
    }
    header('Location: schedule.php');
    exit;
}
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$rows = q('SELECT s.*, u.ename, u.login_id FROM schedules s JOIN users u ON u.id = s.student_id ORDER BY s.test_date DESC, s.test_time DESC')->fetchAll();
$students = q("SELECT id, ename, login_id FROM users WHERE user_type = 'student' ORDER BY ename")->fetchAll();

admin_head('schedule');
?>
      <div class="schedule-container">
<?php topbar('Schedule Management', 'Manage student test schedules', false); ?>
<?php if ($flash): ?>
        <div class="flash <?= $flash[0] ?>"><?= h($flash[1]) ?></div>
<?php endif; ?>

        <div id="scheduleBanner" class="schedule-banner" style="display:none">
          <span class="banner-close" onclick="document.getElementById('scheduleBanner').style.display='none'">&times;</span>
          <div id="bannerBody"></div>
        </div>

        <button class="add-schedule-btn" onclick="openAddModal()"><i class="fa-solid fa-plus"></i> Add New Schedule</button>

        <table class="schedule-table">
          <tr><th>Student</th><th>Date</th><th>Time</th><th>Subject</th><th>Type</th><th>Location</th><th>Notes</th><th>Actions</th><th>View</th></tr>
<?php if (!$rows): ?>
          <tr><td colspan="9" class="empty-row">No schedules.</td></tr>
<?php endif; ?>
<?php foreach ($rows as $r): $js = h(json_encode($r, JSON_UNESCAPED_UNICODE)); ?>
          <tr>
            <td><?= h($r['ename']) ?></td>
            <td><?= h($r['test_date']) ?></td>
            <td><?= h(substr($r['test_time'], 0, 5)) ?></td>
            <td><?= h($r['test_subject']) ?></td>
            <td><?= h($r['test_type']) ?></td>
            <td><?= h($r['location']) ?></td>
            <td><?= h($r['notes']) ?></td>
            <td>
              <button class="action-btn edit-btn" onclick="openEditModal(<?= $js ?>)"><i class="fa-solid fa-pen-to-square"></i></button>
              <button class="action-btn delete-btn" onclick="deleteSchedule(<?= (int)$r['id'] ?>)"><i class="fa-solid fa-trash"></i></button>
            </td>
            <td><button class="action-btn view-btn" onclick="showBanner(<?= $js ?>)"><i class="fa-solid fa-eye"></i> View</button></td>
          </tr>
<?php endforeach; ?>
        </table>
      </div>

      <div id="scheduleModal" class="modal">
        <div class="modal-content">
          <span class="close" onclick="closeModal()">&times;</span>
          <h2 id="modalTitle">Add New Schedule</h2>
          <form method="post" id="scheduleForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="scheduleId">
            <div class="form-group">
              <label for="student_id">Student</label>
              <select name="student_id" id="student_id" required>
                <option value="">Select Student</option>
<?php foreach ($students as $s): ?>
                <option value="<?= (int)$s['id'] ?>"><?= h($s['ename']) ?> (<?= h($s['login_id']) ?>)</option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="form-row">
              <div class="form-group"><label for="test_date">Test Date</label><input type="date" name="test_date" id="test_date" required></div>
              <div class="form-group"><label for="test_time">Test Time</label><input type="time" name="test_time" id="test_time" required></div>
            </div>
            <div class="form-row">
              <div class="form-group"><label for="test_subject">Test Subject</label><input type="text" name="test_subject" id="test_subject"></div>
              <div class="form-group"><label for="test_type">Test Type</label>
                <select name="test_type" id="test_type">
                  <option value="">Select Type</option>
<?php foreach (SCHEDULE_TYPES as $t): ?>
                  <option value="<?= h($t) ?>"><?= h($t) ?></option>
<?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="form-group"><label for="location">Location</label><input type="text" name="location" id="location" placeholder="e.g., Room 101"></div>
            <div class="form-group"><label for="notes">Notes</label><textarea name="notes" id="notes" rows="3" placeholder="Additional notes..."></textarea></div>
            <div class="modal-buttons">
              <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
              <button type="submit" class="btn btn-primary">Save Schedule</button>
            </div>
          </form>
        </div>
      </div>

      <form method="post" id="deleteForm" style="display:none">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteId">
      </form>
<?php admin_foot(<<<'JS'
<script>
const modal = document.getElementById('scheduleModal');
function openAddModal() {
  document.getElementById('scheduleForm').reset();
  document.getElementById('modalTitle').textContent = 'Add New Schedule';
  document.getElementById('formAction').value = 'add';
  document.getElementById('scheduleId').value = '';
  modal.style.display = 'block';
}
function openEditModal(s) {
  document.getElementById('modalTitle').textContent = 'Edit Schedule';
  document.getElementById('formAction').value = 'edit';
  document.getElementById('scheduleId').value = s.id;
  document.getElementById('student_id').value = s.student_id;
  document.getElementById('test_date').value = s.test_date;
  document.getElementById('test_time').value = (s.test_time || '').slice(0, 5);
  document.getElementById('test_subject').value = s.test_subject || '';
  document.getElementById('test_type').value = s.test_type || '';
  document.getElementById('location').value = s.location || '';
  document.getElementById('notes').value = s.notes || '';
  modal.style.display = 'block';
}
function closeModal() { modal.style.display = 'none'; }
window.addEventListener('click', e => { if (e.target === modal) closeModal(); });
function deleteSchedule(id) {
  if (!confirm('Are you sure you want to delete this schedule?')) return;
  document.getElementById('deleteId').value = id;
  document.getElementById('deleteForm').submit();
}
function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function showBanner(s) {
  document.getElementById('bannerBody').innerHTML =
    '<strong>스케줄 정보</strong><br>' +
    '<strong>학생:</strong> ' + esc(s.ename) + '<br>' +
    '<strong>날짜:</strong> ' + esc(s.test_date) + ' <strong>시간:</strong> ' + esc(s.test_time) + '<br>' +
    '<strong>과목:</strong> ' + esc(s.test_subject) + ' <strong>유형:</strong> ' + esc(s.test_type) + '<br>' +
    '<strong>장소:</strong> ' + esc(s.location) + '<br>' +
    '<strong>비고:</strong> ' + esc(s.notes);
  document.getElementById('scheduleBanner').style.display = 'block';
  window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>
JS);
