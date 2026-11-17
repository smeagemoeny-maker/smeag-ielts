<?php
// Reading / Writing 안내 페이지
require __DIR__ . '/context.php';
$min = SECTION_MINUTES[$section];
$isSingle = set_single_mode($singleKey);   // Individual Skill Test 면 이 영역만 보고 끝
tw_head(ucfirst($section) . ' Test Instructions');
?>
<main class="container mx-auto flex-grow p-6">
  <div class="bg-white rounded-2xl shadow-xl p-8 max-w-4xl mx-auto fade-in">
    <h1 class="text-3xl font-bold text-purple-700 mb-4"><?= ucfirst($section) ?> Test</h1>
    <p class="text-gray-600 mb-6">Please read the instructions carefully before starting the <?= $section ?> test.</p>
    <div class="bg-purple-50 p-6 rounded-lg border border-purple-200">
      <h2 class="text-xl font-semibold text-purple-600 mb-4">Test Information</h2>
      <p class="text-gray-700 mb-2">⏰ <strong>Test Duration:</strong> <?= $min ?> minutes</p>
      <h3 class="text-lg font-semibold text-purple-600 mt-4">Important Notes:</h3>
      <ul class="list-disc pl-6 text-gray-700 space-y-2">
        <li>After <strong class="text-purple-600"><?= $min ?> minutes</strong>, your answers are submitted automatically<?= $isSingle ? ' and your result is shown' : ($section === 'writing' ? ' and the test ends' : ' and the test proceeds to the next section') ?>.</li>
<?php if ($isSingle): ?>
        <li>This is an <strong class="text-purple-600">Individual Skill Test</strong>: only the <?= ucfirst($section) ?> section is taken.</li>
<?php endif; ?>
        <li>Answers not submitted within <strong class="text-purple-600"><?= $min ?> minutes</strong> will be invalidated.</li>
        <li>If the computer shuts down (e.g. a power cut), log in again and click <strong class="text-purple-600">Resume Test</strong> on the home page. Your answers are kept and the timer continues from where it stopped.</li>
        <li>The test opens in <strong class="text-purple-600">full screen</strong>. You may press <strong class="text-purple-600">Esc</strong> to leave full screen, but do not switch to other programs (e.g. Alt+Tab). If you do, the test is hidden until you return, and the timer keeps running.</li>
      </ul>
    </div>
    <div class="mt-6 text-right">
      <a href="<?= $section ?>corse.php" class="bg-green-500 text-white px-6 py-2 rounded-lg hover:bg-green-600 transition">Enter Test</a>
    </div>
  </div>
</main>
<?php tw_foot();
