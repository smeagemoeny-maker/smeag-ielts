<?php
// 학생용 Listening 안내 + 연습문제 (원본 DOM·문구 그대로, 서버 요청 없음)
require __DIR__ . '/context.php';
tw_head('Listening Test Instructions');
?>
<main class="container mx-auto flex-grow p-6">
  <div id="instruction-container" class="bg-white rounded-2xl shadow-xl p-8 max-w-4xl mx-auto">
    <div class="mb-8">
      <div class="w-full bg-gray-200 rounded-full h-2.5">
        <div id="progress-bar" class="bg-purple-500 h-2.5 rounded-full progress-bar" style="width: 33%"></div>
      </div>
    </div>

    <div id="page1" class="page-content fade-in">
      <h1 class="text-3xl font-bold text-purple-700 mb-4">Welcome, <?= h($me['ename']) ?>!</h1>
      <p class="text-gray-600 mb-6">Please read the instructions carefully before starting the listening test.</p>
      <div class="bg-purple-50 p-6 rounded-lg border border-purple-200">
        <h2 class="text-xl font-semibold text-purple-600 mb-4">Test Information</h2>
        <p class="text-gray-700 mb-2">⏰ <strong>Test Duration:</strong> the length of the audio (about 30 minutes)</p>
        <h3 class="text-lg font-semibold text-purple-600 mt-4">Important Notes:</h3>
        <ul class="list-disc pl-6 text-gray-700 space-y-2">
          <li>The test has <strong class="text-purple-600">4 parts</strong> (Questions 1-10, 11-20, 21-30, 31-40). Use the Part buttons to move between them.</li>
          <li>When the audio ends, you will have <strong class="text-purple-600">30 seconds</strong> to check your answers. Then your answers are submitted automatically and the test moves on to Reading.</li>
          <li>If the computer shuts down, follow the examiner's instructions.</li>
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
        <script>try { sessionStorage.removeItem(<?= json_encode('lp-audio-start-' . $setName) ?>); } catch (e) {}</script>
      </div>
      <div class="mt-6 text-right">
        <button id="next-to-page2" class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition">Next</button>
      </div>
    </div>

    <div id="page2" class="page-content fade-in hidden">
      <h1 class="text-3xl font-bold text-purple-700 mb-4">Practice Questions</h1>
      <p class="text-gray-600 mb-6">Try these example questions to familiarize yourself with the test format.</p>
      <div class="bg-purple-50 p-6 rounded-lg border border-purple-200 mb-6">
        <h2 class="text-xl font-semibold text-purple-600 mb-4">Multiple Choice Question</h2>
        <p class="text-gray-700 mb-4">Which country are you in right now?</p>
        <div class="space-y-3">
          <label class="flex items-center space-x-2"><input type="radio" name="mcq" value="A" class="text-purple-600 focus:ring-purple-500"><span>A. Philippines</span></label>
          <label class="flex items-center space-x-2"><input type="radio" name="mcq" value="B" class="text-purple-600 focus:ring-purple-500"><span>B. England</span></label>
          <label class="flex items-center space-x-2"><input type="radio" name="mcq" value="C" class="text-purple-600 focus:ring-purple-500"><span>C. Africa</span></label>
          <label class="flex items-center space-x-2"><input type="radio" name="mcq" value="D" class="text-purple-600 focus:ring-purple-500"><span>D. Mongolia</span></label>
        </div>
        <button id="submit-mcq" class="mt-4 bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition">Submit</button>
        <p id="mcq-feedback" class="mt-2 text-sm text-gray-600 hidden"></p>
      </div>
      <div class="bg-purple-50 p-6 rounded-lg border border-purple-200">
        <h2 class="text-xl font-semibold text-purple-600 mb-4">Short Answer Question</h2>
        <p class="text-gray-700 mb-4">Write a morning greeting in English.</p>
        <input id="short-answer" type="text" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-purple-500 focus:border-purple-500" placeholder="Enter your answer here">
        <button id="submit-short-answer" class="mt-4 bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition">Submit</button>
        <p id="short-answer-feedback" class="mt-2 text-sm text-gray-600 hidden"></p>
      </div>
      <div id="test-entry" class="mt-6 text-right hidden">
        <a href="listeningcorse.php" class="bg-green-500 text-white px-6 py-2 rounded-lg hover:bg-green-600 transition">Enter Test</a>
      </div>
    </div>
  </div>
</main>
<?php tw_foot(<<<'JS'
<script>
$(function () {
  let mcqOk = false, shortOk = false;
  function check() {
    if (mcqOk && shortOk) { $('#progress-bar').css('width', '100%'); $('#test-entry').removeClass('hidden'); }
    else { $('#progress-bar').css('width', '66%'); $('#test-entry').addClass('hidden'); }
  }
  $('#next-to-page2').on('click', function () {
    $('#page1').addClass('hidden'); $('#page2').removeClass('hidden'); $('#progress-bar').css('width', '66%');
  });
  $('#submit-mcq').on('click', function () {
    mcqOk = $('input[name=mcq]:checked').val() === 'A';
    $('#mcq-feedback').removeClass('hidden text-gray-600 text-green-600 text-red-600')
      .addClass(mcqOk ? 'text-green-600' : 'text-red-600')
      .text(mcqOk ? 'Correct! You are in the Philippines.' : 'Incorrect. The correct answer is Philippines.');
    check();
  });
  $('#submit-short-answer').on('click', function () {
    shortOk = $.trim($('#short-answer').val()).toLowerCase() === 'good morning';
    $('#short-answer-feedback').removeClass('hidden text-gray-600 text-green-600 text-red-600')
      .addClass(shortOk ? 'text-green-600' : 'text-red-600')
      .text(shortOk ? 'Correct! The morning greeting is Good Morning.' : 'Incorrect. The correct answer is Good Morning.');
    check();
  });
});
</script>
JS);
