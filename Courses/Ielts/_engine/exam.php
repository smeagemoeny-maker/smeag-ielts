<?php
// 실제 응시 화면 (listeningcorse / readingcorse / writingcorse 공통)
// 원본 listeningcorse.php 는 확인하지 못해 화면 구성은 새로 설계. 채점은 서버에서 함 (정답을 페이지에 노출하지 않음)
require __DIR__ . '/context.php';

$sec = strtoupper($section);
$minutes = SECTION_MINUTES[$section];
$sessKey = "exam_start_{$setName}_$section";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $started = $_SESSION[$sessKey] ?? time();
    $late = time() - $started > ($minutes * 60) + 120;   // 2분 여유. 넘으면 무효 처리 (안내 문구대로)
    if ($section === 'writing') {
        $t1 = $late ? '' : trim((string)($_POST['task1'] ?? ''));
        $t2 = $late ? '' : trim((string)($_POST['task2'] ?? ''));
        q('INSERT INTO test_attempts (user_id, test_type, test_set, section, answers, writing_task1, writing_task2, score, submitted_at) VALUES (?,?,?,?,?,?,?,0,?)',
            [$me['id'], $testType, $setLabel, $sec, null, $t1, $t2, now()]);
    } else {
        $given = [];
        for ($i = 1; $i <= 40; $i++) $given[] = $late ? '' : trim((string)($_POST["q$i"] ?? ''));
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

tw_head(ucfirst($section) . ' Test - ' . $setLabel);
?>
<main class="container mx-auto flex-grow p-6">
  <form id="exam-form" method="post" class="bg-white rounded-2xl shadow-xl p-8 max-w-5xl mx-auto">
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

<?php if ($section === 'listening'): ?>
    <div class="mb-6"><audio controls class="w-full"><source src="audio/listening.mp3" type="audio/mpeg"></audio></div>
<?php endif; ?>

<?php if (is_file($contentFile)): ?>
    <div class="prose max-w-none mb-8 border border-purple-200 rounded-lg p-6 bg-purple-50"><?php readfile($contentFile); ?></div>
<?php endif; ?>

<?php if ($section === 'writing'): ?>
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
<?php tw_foot('<script>
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
