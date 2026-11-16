<?php
// 학생 Scores — 온라인 응시 기록 + 선생님 점수·코멘트
require_once __DIR__ . '/../includes/student_layout.php';
$me = require_student();
$attempts = q('SELECT test_set, section, score, submitted_at FROM test_attempts WHERE user_id = ? ORDER BY submitted_at DESC LIMIT 30', [$me['id']])->fetchAll();
$scores = q('SELECT * FROM score_entries WHERE user_id = ? ORDER BY test_date DESC LIMIT 10', [$me['id']])->fetchAll();

student_head('scores', 'Scores - SMEAG');
?>
      <div class="etest-title">My Scores</div>
      <div class="etest-desc"><?= h($me['ename']) ?> (<?= h($me['login_id']) ?>) · Course: <?= h($me['course']) ?> · Campus: <?= h($me['campus']) ?></div>

      <div class="check-panel">
        <div class="check-panel-head"><h3>My Test History</h3></div>
        <table class="history-table">
          <tr><th>Date</th><th>Test Set</th><th>Section</th><th>Score</th></tr>
<?php foreach ($attempts as $a): ?>
          <tr>
            <td><?= h($a['submitted_at']) ?></td>
            <td><?= h($a['test_set']) ?></td>
            <td><?= h($a['section']) ?></td>
            <td><?= $a['section'] === 'WRITING' ? '-' : (int)$a['score'] . ' / 40' ?></td>
          </tr>
<?php endforeach; ?>
<?php if (!$attempts): ?>
          <tr><td colspan="4" class="student-empty">No tests yet.</td></tr>
<?php endif; ?>
        </table>
      </div>

      <div class="check-panel">
        <div class="check-panel-head"><h3>Teacher Scores &amp; Comments</h3></div>
<?php foreach ($scores as $s): ?>
        <div class="score-card">
          <div class="head"><?= h($s['test_type']) ?> · <?= h($s['test_date']) ?></div>
          <div class="vals">
<?php foreach (['listening', 'reading', 'speaking', 'writing'] as $k): ?>
<?php if ($s[$k . '_score'] !== null && $s[$k . '_score'] !== ''): ?>
            <span><?= ucfirst($k) ?>: <b><?= h($s[$k . '_score']) ?></b></span>
<?php endif; ?>
<?php endforeach; ?>
          </div>
<?php if ($s['sa_teacher_comment']): ?>
          <p class="comment"><?= h($s['sa_teacher_comment']) ?></p>
<?php endif; ?>
        </div>
<?php endforeach; ?>
<?php if (!$scores): ?>
        <div class="student-empty">No scores yet.</div>
<?php endif; ?>
      </div>
<?php
student_foot();
