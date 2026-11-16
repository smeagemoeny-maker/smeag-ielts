<?php
// 학생 Schedule — 나에게 잡힌 시험 일정
require_once __DIR__ . '/../includes/student_layout.php';
$me = require_student();
$schedules = q('SELECT * FROM schedules WHERE student_id = ? ORDER BY test_date DESC', [$me['id']])->fetchAll();

student_head('schedule', 'Schedule - SMEAG');
?>
      <div class="etest-title">My Schedule</div>
      <div class="check-panel">
        <table class="history-table">
          <tr><th>Date</th><th>Time</th><th>Subject</th><th>Type</th><th>Location</th></tr>
<?php foreach ($schedules as $s): ?>
          <tr>
            <td><?= h($s['test_date']) ?></td>
            <td><?= h(substr((string)$s['test_time'], 0, 5)) ?></td>
            <td><?= h($s['test_subject']) ?></td>
            <td><?= h($s['test_type']) ?></td>
            <td><?= h($s['location']) ?></td>
          </tr>
<?php endforeach; ?>
<?php if (!$schedules): ?>
          <tr><td colspan="5" class="student-empty">No scheduled tests.</td></tr>
<?php endif; ?>
        </table>
      </div>
<?php
student_foot();
