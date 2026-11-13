<?php
// Reading / Writing 안내 페이지
require __DIR__ . '/context.php';
$min = SECTION_MINUTES[$section];
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
        <li>After <strong class="text-purple-600"><?= $min ?> minutes</strong>, the test will automatically proceed to the next section.</li>
        <li>Answers not submitted within <strong class="text-purple-600"><?= $min ?> minutes</strong> will be invalidated.</li>
        <li>If the computer shuts down, follow the examiner's instructions.</li>
      </ul>
    </div>
    <div class="mt-6 text-right">
      <a href="<?= $section ?>corse.php" class="bg-green-500 text-white px-6 py-2 rounded-lg hover:bg-green-600 transition">Enter Test</a>
    </div>
  </div>
</main>
<?php tw_foot();
