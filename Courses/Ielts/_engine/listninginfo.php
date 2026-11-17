<?php
// 학생용 Listening 안내 (연습문제 페이지 없음 → 바로 시험으로)
require __DIR__ . '/context.php';
$isSingle = set_single_mode($singleKey);   // Individual Skill Test 면 Listening 만 보고 끝
tw_head('Listening Test Instructions');
?>
<main class="container mx-auto flex-grow p-6">
  <div id="instruction-container" class="bg-white rounded-2xl shadow-xl p-8 max-w-4xl mx-auto">
    <div id="page1" class="page-content fade-in">
      <h1 class="text-3xl font-bold text-purple-700 mb-4">Welcome, <?= h($me['ename']) ?>!</h1>
      <p class="text-gray-600 mb-6">Please read the instructions carefully before starting the listening test.</p>
      <div class="bg-purple-50 p-6 rounded-lg border border-purple-200">
        <h2 class="text-xl font-semibold text-purple-600 mb-4">Test Information</h2>
        <p class="text-gray-700 mb-2">⏰ <strong>Test Duration:</strong> the length of the audio (about 30 minutes)</p>
        <h3 class="text-lg font-semibold text-purple-600 mt-4">Important Notes:</h3>
        <ul class="list-disc pl-6 text-gray-700 space-y-2">
          <li>The test has <strong class="text-purple-600">4 parts</strong> (Questions 1-10, 11-20, 21-30, 31-40). Use the Part buttons to move between them.</li>
          <li>When the audio ends, you will have <strong class="text-purple-600">30 seconds</strong> to check your answers. Then your answers are submitted automatically<?= $isSingle ? ' and your result is shown' : ' and the test moves on to Reading' ?>.</li>
<?php if ($isSingle): ?>
          <li>This is an <strong class="text-purple-600">Individual Skill Test</strong>: only the Listening section is taken.</li>
<?php endif; ?>
          <li>If the computer shuts down (e.g. a power cut), log in again and click <strong class="text-purple-600">Resume Test</strong> on the home page. Your answers are kept and the audio continues from where it stopped.</li>
        <li>The test opens in <strong class="text-purple-600">full screen</strong>. You may press <strong class="text-purple-600">Esc</strong> to leave full screen, but do not switch to other programs (e.g. Alt+Tab). If you do, the test is hidden until you return, and the timer keeps running.</li>
          <li>Test your audio volume below before starting.</li>
        </ul>
        <div class="mt-4">
          <audio controls class="w-full"><source src="audio/audio_test.wav" type="audio/wav"></audio>
        </div>
<?php if (!is_file(APP_ROOT . "/Courses/Ielts/$kind/$setName/listening/audio/listening.mp3")): ?>
        <h3 class="text-lg font-semibold text-purple-600 mt-6 mb-2">Test Audio (<?= h($setLabel) ?>)</h3>
        <p class="text-gray-600 text-sm mb-2">Check that the audio for this test is connected on this computer.</p>
        <div id="local-audio"></div>
        <script src="<?= $base ?>js/local-audio.js"></script>
        <script>LocalAudio.mount(document.getElementById('local-audio'), <?= (int)$m[2] ?>, { checkOnly: true });</script>
<?php endif; ?>
      </div>
      <div class="mt-6 text-right">
        <a href="listeningcorse.php" class="bg-green-500 text-white px-6 py-2 rounded-lg hover:bg-green-600 transition">Enter Test</a>
      </div>
    </div>
  </div>
</main>
<?php tw_foot();
