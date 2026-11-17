<?php
// 응시 완료 — 전체 시험(3개 영역) 이면 오늘 본 3개 영역, Individual Skill Test 면 방금 본 영역만
require __DIR__ . '/context.php';
$sql = 'SELECT section, score, submitted_at FROM test_attempts WHERE user_id = ? AND test_set = ? AND substr(submitted_at,1,10) = ?';
$params = [$me['id'], $setLabel, date('Y-m-d')];
if ($isSingle) {
    // 방금 낸 1건만
    $rows = q($sql . ' AND section = ? ORDER BY submitted_at DESC, id DESC LIMIT 1', array_merge($params, [strtoupper($section)]))->fetchAll();
} else {
    // 전체 시험: Listening · Reading 의 마지막(최종) 점수만 (같은 날 여러 번 봤어도 한 줄씩, Writing 은 선생님 채점이라 빼고)
    $rows = [];
    foreach (['LISTENING', 'READING'] as $s) {
        $r = q($sql . ' AND section = ? ORDER BY submitted_at DESC, id DESC LIMIT 1', array_merge($params, [$s]))->fetch();
        if ($r) $rows[] = $r;
    }
}
$title = $isSingle ? ucfirst($section) . ' Test Completed' : 'Test Completed';
tw_head($title);
?>
<main class="container mx-auto flex-grow p-6">
  <div class="bg-white rounded-2xl shadow-xl p-8 max-w-3xl mx-auto fade-in text-center">
    <h1 class="text-3xl font-bold text-purple-700 mb-4"><?= h($title) ?></h1>
    <p class="text-gray-600 mb-6">Thank you, <?= h($me['ename']) ?>. Your <?= $isSingle ? h($section) . ' ' : '' ?>answers for <?= h($setLabel) ?> have been submitted.</p>
    <table class="mx-auto text-left mb-6">
<?php foreach ($rows as $r): ?>
      <tr><td class="pr-8 py-1 font-semibold text-purple-600"><?= ucfirst(strtolower($r['section'])) ?></td>
          <td class="py-1"><?= $r['section'] === 'WRITING' ? 'Submitted (graded by teacher)' : (int)$r['score'] . ' / 40' ?></td></tr>
<?php endforeach; ?>
    </table>
<?php if ($isSingle): ?>
    <a href="<?= $base ?>student/skill.php?<?= h(http_build_query(['kind' => $kind, 'skill' => $section])) ?>" class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition">Take Another Skill Test</a>
    <a href="<?= $base ?>student/scores.php?<?= h(http_build_query(['type' => 'IELTS ' . strtoupper($kind) . ' TEST', 'subtype' => ucfirst($section)])) ?>" class="ml-2 bg-white text-purple-700 border border-purple-600 px-6 py-2 rounded-lg hover:bg-purple-50 transition">My Scores</a>
<?php else: ?>
    <a href="<?= $base ?>student/index.php" class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition">Back to My Tests</a>
<?php endif; ?>
  </div>
</main>
<?php tw_foot();
