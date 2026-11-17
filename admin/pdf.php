<?php
// 성적표 (브라우저 인쇄 → "PDF로 저장"). 서버 PDF 라이브러리가 필요하면 dompdf/mPDF 로 교체.
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$course = $_GET['course'] ?? '';
$date = $_GET['date'] ?? '';
$u = q('SELECT * FROM users WHERE id = ?', [$id])->fetch();
if (!$u) { http_response_code(404); exit('Student not found'); }

$score = q('SELECT * FROM score_entries WHERE user_id = ? AND test_type = ? AND test_date = ? ORDER BY id DESC LIMIT 1', [$id, $course, $date])->fetch();
$sql = 'SELECT * FROM test_attempts WHERE user_id = ? AND test_type = ? AND substr(submitted_at,1,10) = ?';
$params = [$id, $course, $date];
if (!empty($_GET['test_set'])) { $sql .= ' AND test_set = ?'; $params[] = $_GET['test_set']; }
$attempts = q($sql . ' ORDER BY submitted_at', $params)->fetchAll();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Score Report - <?= h($u['ename']) ?> - <?= h($date) ?></title>
<style>
  body { font-family: 'Inter', 'Pretendard', Arial, sans-serif; color: #222; max-width: 800px; margin: 30px auto; padding: 0 24px; }
  h1 { color: #e74c3c; letter-spacing: 2px; margin: 0; }
  h2 { color: #6c63ff; border-bottom: 2px solid #6c63ff; padding-bottom: 6px; }
  table { width: 100%; border-collapse: collapse; margin: 10px 0 24px; }
  th, td { border: 1px solid #ddd; padding: 8px 10px; text-align: left; }
  th { background: #f3f0ff; color: #6c63ff; width: 30%; }
  .comment { white-space: pre-wrap; border: 1px solid #ddd; padding: 12px; min-height: 60px; }
  .noprint { margin-bottom: 20px; }
  @media print { .noprint { display: none; } }
</style>
</head>
<body>
<div class="noprint"><button onclick="window.print()">Print / Save as PDF</button></div>
<h1><img src="../images/smeag_logo.png" alt="SMEAG GLOBAL EDUCATION" style="display:block;width:240px;height:auto;"></h1>
<p>Test Score Report</p>

<h2>Student</h2>
<table>
  <tr><th>Name</th><td><?= h($u['ename']) ?></td></tr>
  <tr><th>ID</th><td><?= h($u['login_id']) ?></td></tr>
  <tr><th>Course</th><td><?= h($course) ?></td></tr>
  <tr><th>Campus</th><td><?= h($u['campus']) ?></td></tr>
  <tr><th>Test Date</th><td><?= h($date) ?></td></tr>
</table>

<?php if ($attempts): ?>
<h2>Online Test Results</h2>
<table>
  <tr><th>Test Set</th><th>Section</th><th>Submitted</th><th>Score</th></tr>
<?php foreach ($attempts as $a): ?>
  <tr><td><?= h($a['test_set']) ?></td><td><?= h($a['section']) ?></td><td><?= h($a['submitted_at']) ?></td>
      <td><?= $a['section'] === 'WRITING' ? '-' : (int)$a['score'] . ' / 40' ?></td></tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<?php if ($score): ?>
<h2>Teacher Score</h2>
<table>
<?php foreach (['listening' => 'Listening', 'reading' => 'Reading', 'speaking' => 'Speaking', 'writing' => 'Writing'] as $k => $label): ?>
<?php if ($score[$k . '_score'] !== null && $score[$k . '_score'] !== ''): ?>
  <tr><th><?= $label ?></th><td><?= h($score[$k . '_score']) ?></td></tr>
<?php endif; ?>
<?php endforeach; ?>
</table>
<h2>SA Teacher Comment</h2>
<div class="comment"><?= h($score['sa_teacher_comment']) ?></div>
<?php endif; ?>

<?php if (!$attempts && !$score): ?>
<p>No data for this date.</p>
<?php endif; ?>
<script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
</body>
</html>
