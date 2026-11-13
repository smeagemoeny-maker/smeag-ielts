<?php
// 3개 영역 응시 완료
require __DIR__ . '/context.php';
$rows = q("SELECT section, score, submitted_at FROM test_attempts WHERE user_id = ? AND test_set = ? AND substr(submitted_at,1,10) = ?
           ORDER BY submitted_at", [$me['id'], $setLabel, date('Y-m-d')])->fetchAll();
tw_head('Test Completed');
?>
<main class="container mx-auto flex-grow p-6">
  <div class="bg-white rounded-2xl shadow-xl p-8 max-w-3xl mx-auto fade-in text-center">
    <h1 class="text-3xl font-bold text-purple-700 mb-4">Test Completed</h1>
    <p class="text-gray-600 mb-6">Thank you, <?= h($me['ename']) ?>. Your answers for <?= h($setLabel) ?> have been submitted.</p>
    <table class="mx-auto text-left mb-6">
<?php foreach ($rows as $r): ?>
      <tr><td class="pr-8 py-1 font-semibold text-purple-600"><?= ucfirst(strtolower($r['section'])) ?></td>
          <td class="py-1"><?= $r['section'] === 'WRITING' ? 'Submitted (graded by teacher)' : (int)$r['score'] . ' / 40' ?></td></tr>
<?php endforeach; ?>
    </table>
    <a href="<?= $base ?>student/index.php" class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition">Back to My Tests</a>
  </div>
</main>
<?php tw_foot();
