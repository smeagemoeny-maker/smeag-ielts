<?php
// 학생 로그인 후 화면 (원본은 학생 계정으로 확인하지 못해 새로 설계: 응시 세트 선택 + 내 성적)
require_once __DIR__ . '/../includes/bootstrap.php';
$me = require_login('../');
if ($me['user_type'] === 'admin') { header('Location: ../admin/admin_page.php'); exit; }

$kind = $me['course'] === 'IELTS GENERAL' ? 'general' : 'academic';
$prefix = strtoupper($kind);
$isIelts = str_starts_with((string)$me['course'], 'IELTS');
$attempts = q('SELECT test_set, section, score, submitted_at FROM test_attempts WHERE user_id = ? ORDER BY submitted_at DESC LIMIT 30', [$me['id']])->fetchAll();
$scores = q('SELECT * FROM score_entries WHERE user_id = ? ORDER BY test_date DESC LIMIT 10', [$me['id']])->fetchAll();
$schedules = q('SELECT * FROM schedules WHERE student_id = ? ORDER BY test_date DESC', [$me['id']])->fetchAll();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Tests - SMEAG</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #f3e8ff 0%, #e9d5ff 100%); }</style>
</head>
<body class="min-h-screen flex flex-col">
<nav class="bg-gradient-to-r from-purple-600 to-purple-400 text-white p-4 shadow-lg">
  <div class="container mx-auto flex justify-between items-center">
    <div class="text-2xl font-extrabold tracking-widest">SMEAG</div>
    <div class="space-x-4"><span><?= h($me['ename']) ?> (<?= h($me['login_id']) ?>)</span><a href="../logout.php" class="underline">Logout</a></div>
  </div>
</nav>
<main class="container mx-auto flex-grow p-6 space-y-6 max-w-5xl">
  <section class="bg-white rounded-2xl shadow-xl p-8">
    <h1 class="text-2xl font-bold text-purple-700 mb-2">Welcome, <?= h($me['ename']) ?>!</h1>
    <p class="text-gray-600 mb-6">Course: <?= h($me['course']) ?> · Campus: <?= h($me['campus']) ?></p>
<?php if ($isIelts): ?>
    <h2 class="text-lg font-semibold text-purple-600 mb-3">Available <?= $prefix ?> Test Sets</h2>
    <div class="flex flex-wrap gap-3">
<?php for ($n = 1; $n <= SET_COUNT; $n++): ?>
      <a href="../Courses/Ielts/<?= $kind ?>/<?= $prefix . $n ?>/listening/listninginfo.php" class="px-5 py-3 rounded-xl bg-purple-50 text-purple-700 font-semibold hover:bg-purple-600 hover:text-white transition"><?= $prefix ?> <?= $n ?></a>
<?php endfor; ?>
    </div>
<?php else: ?>
    <p class="text-gray-500">Online tests for your course are not available yet.</p>
<?php endif; ?>
  </section>

  <section class="bg-white rounded-2xl shadow-xl p-8">
    <h2 class="text-lg font-semibold text-purple-600 mb-3">My Test History</h2>
    <table class="w-full text-left text-sm">
      <tr class="bg-purple-50 text-purple-700"><th class="p-2">Date</th><th class="p-2">Test Set</th><th class="p-2">Section</th><th class="p-2">Score</th></tr>
<?php foreach ($attempts as $a): ?>
      <tr class="border-b"><td class="p-2"><?= h($a['submitted_at']) ?></td><td class="p-2"><?= h($a['test_set']) ?></td><td class="p-2"><?= h($a['section']) ?></td>
        <td class="p-2"><?= $a['section'] === 'WRITING' ? '-' : (int)$a['score'] . ' / 40' ?></td></tr>
<?php endforeach; ?>
<?php if (!$attempts): ?><tr><td colspan="4" class="p-4 text-center text-gray-400">No tests yet.</td></tr><?php endif; ?>
    </table>
  </section>

<?php if ($scores): ?>
  <section class="bg-white rounded-2xl shadow-xl p-8">
    <h2 class="text-lg font-semibold text-purple-600 mb-3">Teacher Scores &amp; Comments</h2>
<?php foreach ($scores as $s): ?>
    <div class="border border-purple-100 rounded-lg p-4 mb-3">
      <div class="font-semibold"><?= h($s['test_type']) ?> · <?= h($s['test_date']) ?></div>
      <div class="text-sm text-gray-700">
        <?php foreach (['listening', 'reading', 'speaking', 'writing'] as $k) if ($s[$k . '_score'] !== null && $s[$k . '_score'] !== '') echo ucfirst($k) . ': <b>' . h($s[$k . '_score']) . '</b> &nbsp; '; ?>
      </div>
      <?php if ($s['sa_teacher_comment']): ?><p class="mt-2 text-gray-600 whitespace-pre-line"><?= h($s['sa_teacher_comment']) ?></p><?php endif; ?>
    </div>
<?php endforeach; ?>
  </section>
<?php endif; ?>

<?php if ($schedules): ?>
  <section class="bg-white rounded-2xl shadow-xl p-8">
    <h2 class="text-lg font-semibold text-purple-600 mb-3">My Schedule</h2>
<?php foreach ($schedules as $s): ?>
    <p class="text-gray-700"><?= h($s['test_date']) ?> <?= h(substr($s['test_time'], 0, 5)) ?> · <?= h($s['test_subject']) ?> (<?= h($s['test_type']) ?>) · <?= h($s['location']) ?></p>
<?php endforeach; ?>
  </section>
<?php endif; ?>
</main>
<footer class="bg-gray-800 text-white text-center p-4"><p>&copy; 2025 Test Platform. All rights reserved.</p></footer>
</body>
</html>
