<?php
// 실제 응시 화면 (listeningcorse / readingcorse / writingcorse 공통)
// 원본 listeningcorse.php 는 확인하지 못해 화면 구성은 새로 설계. 채점은 서버에서 함 (정답을 페이지에 노출하지 않음)
require __DIR__ . '/context.php';

$sec = strtoupper($section);
$minutes = SECTION_MINUTES[$section];
$sessKey = "exam_start_{$setName}_$section";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $started = $_SESSION[$sessKey] ?? time();
    // 2분 여유. 넘으면 무효 처리 (안내 문구대로). Listening 은 시간 제한 없음 (음원 끝 + 30초 뒤 자동 제출)
    $late = $section !== 'listening' && time() - $started > ($minutes * 60) + 120;
    if ($section === 'writing') {
        $t1 = $late ? '' : trim((string)($_POST['task1'] ?? ''));
        $t2 = $late ? '' : trim((string)($_POST['task2'] ?? ''));
        q('INSERT INTO test_attempts (user_id, test_type, test_set, section, answers, writing_task1, writing_task2, score, submitted_at) VALUES (?,?,?,?,?,?,?,0,?)',
            [$me['id'], $testType, $setLabel, $sec, null, $t1, $t2, now()]);
    } else {
        $given = [];
        for ($i = 1; $i <= 40; $i++) {
            $v = $_POST["q$i"] ?? '';
            if (is_array($v)) $v = implode(',', array_map('strval', $v));   // 여러 개 고르는 문항 (체크박스 q21[])
            $given[] = $late ? '' : trim((string)$v);
        }
        $score = grade($given, answer_key($setLabel, $sec));
        q('INSERT INTO test_attempts (user_id, test_type, test_set, section, answers, score, submitted_at) VALUES (?,?,?,?,?,?,?)',
            [$me['id'], $testType, $setLabel, $sec, json_encode($given, JSON_UNESCAPED_UNICODE), $score, now()]);
    }
    unset($_SESSION[$sessKey]);
    header('Location: ' . NEXT_SECTION[$section]);
    exit;
}

$_SESSION[$sessKey] ??= time();
$remaining = max(0, $minutes * 60 - (time() - $_SESSION[$sessKey]));
$hasContent = is_file($contentFile);
// 세트별 음원: Courses/Ielts/{kind}/{SET}/listening/audio/listening.mp3 가 서버에 있으면 그것을,
// 없으면 각 PC 의 음원 폴더 "SET N AUDIO.mp3" (js/local-audio.js — ACADEMIC N · GENERAL N 듣기는 같은 음원)
$audioFile = dirname($contentFile) . '/audio/listening.mp3';
$audioUrl = is_file($audioFile) ? 'audio/listening.mp3' : '';
$setNo = (int)$m[2];

// Listening: 컴퓨터 시험(CD IELTS) 모양의 별도 화면 (css/listening-exam.css + js/listening-parts.js)
// 시간 제한 없음 → 음원이 끝나면 30초 답안 확인 시간 뒤 자동 제출 (→ Reading 안내)
if ($section === 'listening') {
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Listening Test - <?= h($setLabel) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="<?= $base ?>css/listening-exam.css">
<link rel="stylesheet" href="<?= $base ?>css/exam-tools.css">
</head>
<body class="lp-body">
<form id="exam-form" method="post">
  <div class="lp-top">
    <div class="lp-who"><b><?= h($me['ename']) ?></b><span><?= h($setLabel) ?> · Listening</span></div>
    <div id="exam-audio" class="lp-audio"></div>
    <div class="lp-status">
      <div id="timer-label">Listening</div>
      <div id="timer">Audio in progress</div>
    </div>
  </div>

  <main class="lp-main">
    <div id="part-head" class="lp-part-head"></div>
    <div id="exam-content">
<?php if ($hasContent): ?>
      <?php readfile($contentFile); ?>
<?php else: ?>
      <div class="legacy-content">
        <p style="margin-bottom:12px">Write your answers.</p>
<?php for ($i = 1; $i <= 40; $i++): ?>
        <div style="margin:6px 0"><b><?= $i ?></b> <input type="text" name="q<?= $i ?>" autocomplete="off"></div>
<?php endfor; ?>
      </div>
<?php endif; ?>
    </div>
  </main>

  <div id="lp-arrows" class="lp-arrows">
    <button type="button" data-step="-1" title="Previous question"><svg viewBox="0 0 24 24" width="30" height="30"><path d="M20 12H6M12 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="3.2"/></svg></button>
    <button type="button" data-step="1" title="Next question"><svg viewBox="0 0 24 24" width="30" height="30"><path d="M4 12h14M12 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="3.2"/></svg></button>
  </div>
  <div id="lp-nav" class="lp-nav"></div>
</form>
<script src="<?= $base ?>js/listening-parts.js"></script>
<script src="<?= $base ?>js/local-audio.js"></script>
<script src="<?= $base ?>js/exam-audio.js"></script>
<script src="<?= $base ?>js/exam-tools.js"></script>
<script src="<?= $base ?>js/exam-lock.js"></script>
<script>
const form = document.getElementById("exam-form");
const AUDIO_KEY = <?= json_encode('lp-audio-start-' . $setName) ?>;
function submitNow() { form.submit(); }
// 위 막대 도구: 연결 상태 · Options (제출 / Contrast / Text size) · Notes. 연결이 끊기면 제출을 미뤘다가 다시 연결되면 보냄
const tools = ExamTools.mount({ form: form, top: document.querySelector(".lp-top"), key: <?= json_encode($me['id'] . ':' . $setName . ':' . $section . ':' . $_SESSION[$sessKey]) ?>, beforeSend: function () { ExamAudio.clear(AUDIO_KEY); } });
ListeningParts.init({
  root: document.getElementById("exam-content"),
  header: document.getElementById("part-head"),
  nav: document.getElementById("lp-nav"),
  arrows: document.getElementById("lp-arrows"),
  onSubmit: function () { tools.openSubmission(); }
});
// Enter 로 실수 제출 막기
form.addEventListener("keydown", function (e) { if (e.key === "Enter" && e.target.tagName === "INPUT") e.preventDefault(); });

// 전체 화면 [Start Test] 를 누른 뒤 음원 시작
// 음원: 재생기 없이 자동 재생 (멈춤·이동 불가). 끝나면 30초 답안 확인 시간 뒤 자동 제출 → Reading 안내
const CHECK_SECONDS = 30;
ExamLock.init({ key: <?= json_encode('listening-' . $setName) ?>, title: "Listening Test", onStart: function () {
ExamAudio.start({
  box: document.getElementById("exam-audio"),
  setNo: <?= $setNo ?>,
  serverUrl: <?= json_encode($audioUrl) ?>,
  key: AUDIO_KEY,
  onEnded: function () {
    let left = CHECK_SECONDS;
    document.getElementById("timer-label").textContent = "Check your answers";
    const t = document.getElementById("timer");
    t.classList.add("lp-urgent");
    (function count() {
      t.textContent = "0:" + String(left).padStart(2, "0");
      if (left <= 0) { submitNow(); return; }
      left--;
      setTimeout(count, 1000);
    })();
  }
});
} });
</script>
</body>
</html>
<?php
    exit;
}

// Reading: 컴퓨터 시험(CD IELTS) 모양 — 지문 3개 = Part 1~3, 왼쪽 지문 / 오른쪽 문제, 60분 제한 (끝나면 자동 제출 → Writing 안내)
if ($section === 'reading' && $hasContent) {
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reading Test - <?= h($setLabel) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="<?= $base ?>css/listening-exam.css">
<link rel="stylesheet" href="<?= $base ?>css/exam-tools.css">
<link rel="stylesheet" href="<?= $base ?>css/reading-exam.css">
</head>
<body class="lp-body rd-body">
<form id="exam-form" method="post">
  <div class="lp-top">
    <div class="lp-who"><b><?= h($me['ename']) ?></b><span><?= h($setLabel) ?> · Reading</span></div>
    <div class="lp-status">
      <div id="timer-label">Time Remaining</div>
      <div id="timer">--:--</div>
    </div>
  </div>

  <main class="lp-main">
    <div id="part-head" class="lp-part-head"></div>
    <div id="exam-content">
      <?php readfile($contentFile); ?>
    </div>
  </main>

  <div id="lp-arrows" class="lp-arrows">
    <button type="button" data-step="-1" title="Previous question"><svg viewBox="0 0 24 24" width="30" height="30"><path d="M20 12H6M12 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="3.2"/></svg></button>
    <button type="button" data-step="1" title="Next question"><svg viewBox="0 0 24 24" width="30" height="30"><path d="M4 12h14M12 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="3.2"/></svg></button>
  </div>
  <div id="lp-nav" class="lp-nav"></div>
</form>
<script src="<?= $base ?>js/listening-parts.js"></script>
<script src="<?= $base ?>js/reading-layout.js"></script>
<script src="<?= $base ?>js/exam-tools.js"></script>
<script src="<?= $base ?>js/exam-lock.js"></script>
<script>
const form = document.getElementById("exam-form");
const root = document.getElementById("exam-content");
// 전체 화면 + 다른 프로그램으로 넘어가기 막기/경고
ExamLock.init({ key: <?= json_encode('reading-' . $setName) ?>, title: "Reading Test" });
// 위 막대 도구: 연결 상태 · Options (제출 / Contrast / Text size) · Notes
const tools = ExamTools.mount({ form: form, top: document.querySelector(".lp-top"), key: <?= json_encode($me['id'] . ':' . $setName . ':' . $section . ':' . $_SESSION[$sessKey]) ?> });
ListeningParts.init({
  root: root,
  header: document.getElementById("part-head"),
  nav: document.getElementById("lp-nav"),
  arrows: document.getElementById("lp-arrows"),
  partOf: ReadingLayout.build(root),
  intro: "Read the text and answer questions",
  onPart: function () { ReadingLayout.resetScroll(root); },
  onSubmit: function () { tools.openSubmission(); }
});
// Enter 로 실수 제출 막기
form.addEventListener("keydown", function (e) { if (e.key === "Enter" && e.target.tagName === "INPUT") e.preventDefault(); });
// 60분 (서버 시작 시각 기준 남은 시간). 0 이 되면 자동 제출
let remain = <?= (int)$remaining ?>;
const timer = document.getElementById("timer");
(function tick() {
  const m = Math.floor(remain / 60), s = remain % 60;
  timer.textContent = String(m).padStart(2, "0") + ":" + String(s).padStart(2, "0");
  timer.classList.toggle("rd-low", remain <= 600);
  if (remain <= 0) { form.submit(); return; }
  remain--;
  setTimeout(tick, 1000);
})();
</script>
</body>
</html>
<?php
    exit;
}

// Writing: 컴퓨터 시험(CD IELTS) 모양 — Task 1 = Part 1, Task 2 = Part 2, 왼쪽 문제 / 오른쪽 답안,
// 60분 제한 (서버 시작 시각 기준, 끝나면 자동 제출 → 끝 화면). css/writing-exam.css + js/writing-layout.js
if ($section === 'writing' && $hasContent) {
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Writing Test - <?= h($setLabel) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="<?= $base ?>css/listening-exam.css">
<link rel="stylesheet" href="<?= $base ?>css/exam-tools.css">
<link rel="stylesheet" href="<?= $base ?>css/writing-exam.css">
</head>
<body class="lp-body wr-body">
<form id="exam-form" method="post">
  <div class="lp-top">
    <div class="lp-who"><b><?= h($me['ename']) ?></b><span><?= h($setLabel) ?> · Writing</span></div>
    <div class="lp-status">
      <div id="timer-label">Time Remaining</div>
      <div id="timer">--:--</div>
    </div>
  </div>

  <main class="lp-main">
    <div id="part-head" class="lp-part-head"></div>
    <div id="exam-content">
      <?php readfile($contentFile); ?>
    </div>
  </main>

  <div id="lp-arrows" class="lp-arrows">
    <button type="button" data-step="-1" title="Previous part"><svg viewBox="0 0 24 24" width="30" height="30"><path d="M20 12H6M12 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="3.2"/></svg></button>
    <button type="button" data-step="1" title="Next part"><svg viewBox="0 0 24 24" width="30" height="30"><path d="M4 12h14M12 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="3.2"/></svg></button>
  </div>
  <div id="lp-nav" class="lp-nav"></div>
</form>
<script src="<?= $base ?>js/writing-layout.js"></script>
<script src="<?= $base ?>js/exam-tools.js"></script>
<script src="<?= $base ?>js/exam-lock.js"></script>
<script>
const form = document.getElementById("exam-form");
// 전체 화면 + 다른 프로그램으로 넘어가기 막기/경고
ExamLock.init({ key: <?= json_encode('writing-' . $setName) ?>, title: "Writing Test" });
// 위 막대 도구: 연결 상태 · Options (제출 / Contrast / Text size) · Notes
const tools = ExamTools.mount({ form: form, top: document.querySelector(".lp-top"), key: <?= json_encode($me['id'] . ':' . $setName . ':' . $section . ':' . $_SESSION[$sessKey]) ?>, beforeSend: function () { if (writing) writing.clearDraft(); } });
const writing = WritingLayout.init({
  root: document.getElementById("exam-content"),
  header: document.getElementById("part-head"),
  nav: document.getElementById("lp-nav"),
  arrows: document.getElementById("lp-arrows"),
  draftKey: <?= json_encode($me['id'] . ':' . $setName . ':' . $_SESSION[$sessKey]) ?>,
  onSubmit: function () { tools.openSubmission(); }
});
function finish() { form.submit(); }   // 임시 저장은 실제로 보낼 때 지움 (ExamTools beforeSend)
// 60분 (서버 시작 시각 기준 남은 시간). 0 이 되면 자동 제출
let remain = <?= (int)$remaining ?>;
const timer = document.getElementById("timer");
(function tick() {
  const m = Math.floor(remain / 60), s = remain % 60;
  timer.textContent = String(m).padStart(2, "0") + ":" + String(s).padStart(2, "0");
  timer.classList.toggle("wr-low", remain <= 600);
  if (remain <= 0) { finish(); return; }
  remain--;
  setTimeout(tick, 1000);
})();
</script>
</body>
</html>
<?php
    exit;
}

tw_head(ucfirst($section) . ' Test - ' . $setLabel);
?>
<style>
  .legacy-content { overflow-x: auto; color: #1f2937; line-height: 1.6; }
  .legacy-content table { border-collapse: collapse; margin-bottom: 20px; max-width: 100%; }
  .legacy-content th, .legacy-content td { border: 1px solid #000; padding: 8px; text-align: left; }
  .legacy-content input[type=text] { border: 0; border-bottom: 1px solid #6b21a8; background: #faf5ff; padding: 2px 6px; min-width: 120px; max-width: 100%; }
  .legacy-content input[type=text]:focus { outline: 2px solid #a855f7; }
  .legacy-content input[type=radio], .legacy-content input[type=checkbox] { accent-color: #9333ea; margin-right: 6px; }
  .legacy-content textarea { width: 100%; border: 1px solid #d1d5db; border-radius: 8px; padding: 12px; }
  .legacy-content img { max-width: 100%; height: auto; }
  .legacy-content div, .legacy-content table, .legacy-content p { max-width: 100%; box-sizing: border-box; }
  .legacy-content .page + .page { border-top: 2px dashed #d8b4fe; margin-top: 32px; padding-top: 32px; }
</style>
<main class="container mx-auto flex-grow p-6">
  <form id="exam-form" method="post" class="bg-white rounded-2xl shadow-xl p-8 <?= $hasContent && $section === 'reading' ? 'max-w-7xl' : 'max-w-5xl' ?> mx-auto">
    <div class="flex justify-between items-center mb-6">
      <div>
        <h1 class="text-2xl font-bold text-purple-700"><?= h($setLabel) ?> · <?= ucfirst($section) ?></h1>
        <p class="text-gray-600"><?= h($me['ename']) ?></p>
      </div>
      <div class="text-right">
        <div class="text-sm text-gray-500">Time Remaining</div>
        <div id="timer" class="text-3xl font-bold text-purple-600 tabular-nums">--:--</div>
      </div>
    </div>

<?php if ($hasContent): ?>
    <div class="mb-8"><?php readfile($contentFile); ?></div>
<?php elseif ($section === 'writing'): ?>
<?php foreach ([1 => 'at least 150 words', 2 => 'at least 250 words'] as $n => $hint): ?>
    <div class="mb-8">
      <h2 class="text-xl font-semibold text-purple-600 mb-2">Writing Task <?= $n ?> <span class="text-sm text-gray-500 font-normal">(<?= $hint ?>)</span></h2>
      <textarea name="task<?= $n ?>" rows="14" class="w-full p-4 border border-gray-300 rounded-lg focus:ring-purple-500 focus:border-purple-500 wc"></textarea>
      <div class="text-right text-sm text-purple-700">Word Count: <span class="wc-out">0</span></div>
    </div>
<?php endforeach; ?>
<?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-10 gap-y-3">
<?php for ($i = 1; $i <= 40; $i++): ?>
      <label class="flex items-center gap-3">
        <span class="w-8 text-right text-gray-500 font-semibold"><?= $i ?></span>
        <input type="text" name="q<?= $i ?>" autocomplete="off" class="flex-1 p-2 border border-gray-300 rounded-lg focus:ring-purple-500 focus:border-purple-500">
      </label>
<?php endfor; ?>
    </div>
<?php endif; ?>

    <div class="mt-8 text-right">
      <button type="submit" class="bg-purple-600 text-white px-8 py-3 rounded-lg hover:bg-purple-700 transition" onclick="return confirm('Submit your answers?')">Submit</button>
    </div>
  </form>
</main>
<?php
tw_foot('<script>
let remain = ' . $remaining . ';
const form = document.getElementById("exam-form");
function tick() {
  const m = Math.floor(remain / 60), s = remain % 60;
  document.getElementById("timer").textContent = String(m).padStart(2, "0") + ":" + String(s).padStart(2, "0");
  if (remain <= 0) { form.onsubmit = null; form.querySelectorAll("button").forEach(b => b.onclick = null); form.submit(); return; }
  remain--;
  setTimeout(tick, 1000);
}
tick();
document.querySelectorAll(".wc").forEach(t => t.addEventListener("input", () => {
  const n = t.value.trim() ? t.value.trim().split(/\s+/).length : 0;
  t.parentElement.querySelector(".wc-out").textContent = n;
}));
</script>');
