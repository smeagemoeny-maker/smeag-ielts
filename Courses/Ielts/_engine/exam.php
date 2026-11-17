<?php
// 실제 응시 화면 (listeningcorse / readingcorse / writingcorse 공통)
// 원본 listeningcorse.php 는 확인하지 못해 화면 구성은 새로 설계. 채점은 서버에서 함 (정답을 페이지에 노출하지 않음)
require __DIR__ . '/context.php';
require_once APP_ROOT . '/includes/exam_drafts.php';

$sec = strtoupper($section);
$minutes = SECTION_MINUTES[$section];
$limit = $minutes * 60;
$sessKey = "exam_start_{$setName}_$section";

// ---------- 자동 저장 · 이어서 보기 (정전·인터넷 끊김 대비) — includes/exam_drafts.php ----------
// 답안 + 사용한 시간 + Listening 음원 위치를 저장. 다시 열면 (다른 PC 여도) 멈춘 곳에서 이어짐. 제출하면 지움.
$draftWhere = 'user_id = ? AND test_set = ? AND section = ?';
$draftKey = [$me['id'], $setName, $section];
$draftCols = 'answers, started_at, updated_at, elapsed, audio_pos, single_mode, leave_count, fs_exit_count';
// js/exam-lock.js 가 보내는 횟수 (줄어들지 않음): 시험 화면을 떠난 횟수 _leaves · 전체 화면을 나간 횟수 _fsexits
function posted_count($row, string $post, string $col): int
{
    $n = is_numeric($_POST[$post] ?? null) ? (int)$_POST[$post] : 0;
    return min(9999, max(0, $n, $row ? (int)($row[$col] ?? 0) : 0));
}
function posted_leaves($row): int { return posted_count($row, '_leaves', 'leave_count'); }
function posted_fsexits($row): int { return posted_count($row, '_fsexits', 'fs_exit_count'); }

// 처음부터 다시 (Listening: 정전 뒤 다시 열었을 때 [Start from the Beginning]) — 저장된 답안·음원 위치를 지움.
// Individual Skill Test 여부는 세션에 남아 있으므로 다시 열면 같은 방식으로 새로 시작됨
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_restart'])) {
    header('Content-Type: application/json');
    if ($section !== 'listening') { http_response_code(400); exit('{"ok":false}'); }
    $row = draft_q("SELECT single_mode FROM exam_drafts WHERE $draftWhere", $draftKey)->fetch();
    if ($row && (int)$row['single_mode']) $_SESSION[$singleKey] = true;
    draft_q("DELETE FROM exam_drafts WHERE $draftWhere", $draftKey);
    unset($_SESSION[$sessKey]);
    log_activity($me['login_id'], 'Restarted', "$setLabel Listening restarted from the beginning after an interruption");
    exit('{"ok":true}');
}

// 자동 저장 요청 (fetch / sendBeacon). 응시 화면을 열 때 만든 행만 고침 → 제출 뒤 늦게 온 저장은 무시됨
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_autosave'])) {
    header('Content-Type: application/json');
    $data = [];
    foreach ($_POST as $k => $v) {
        if (preg_match('/^(q\d{1,2}|task[12])$/', $k)) $data[$k] = is_array($v) ? array_map('strval', $v) : (string)$v;
    }
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    if (strlen($json) > 200000) { http_response_code(413); exit('{"ok":false}'); }
    $row = draft_q("SELECT $draftCols FROM exam_drafts WHERE $draftWhere", $draftKey)->fetch();
    if (!$row) exit('{"ok":false}');
    [$used, $pos] = draft_progress($row, $_POST['_elapsed'] ?? null, $_POST['_pos'] ?? null);
    $n = draft_q("UPDATE exam_drafts SET answers = ?, elapsed = ?, audio_pos = ?, leave_count = ?, fs_exit_count = ?, updated_at = ? WHERE $draftWhere",
        array_merge([$json, $used, $pos, posted_leaves($row), posted_fsexits($row), time()], $draftKey))->rowCount();
    exit(json_encode(['ok' => $n > 0, 't' => time()]));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 사용한 시간 = 시험 화면이 켜져 있던 시간 (정전으로 꺼져 있던 동안은 빼고)
    $row = draft_q("SELECT $draftCols FROM exam_drafts WHERE $draftWhere", $draftKey)->fetch();
    $used = $row ? draft_progress($row, $_POST['_elapsed'] ?? null, null)[0] : time() - ($_SESSION[$sessKey] ?? time());
    // 2분 여유. 넘으면 무효 처리 (안내 문구대로). Listening 은 시간 제한 없음 (음원 끝 + 30초 뒤 자동 제출)
    $late = $section !== 'listening' && $used > $limit + 120;
    $leaves = posted_leaves($row);
    $fsExits = posted_fsexits($row);
    if ($section === 'writing') {
        $t1 = $late ? '' : trim((string)($_POST['task1'] ?? ''));
        $t2 = $late ? '' : trim((string)($_POST['task2'] ?? ''));
        attempt_q('INSERT INTO test_attempts (user_id, test_type, test_set, section, answers, writing_task1, writing_task2, score, submitted_at, leave_count, fs_exit_count) VALUES (?,?,?,?,?,?,?,0,?,?,?)',
            [$me['id'], $testType, $setLabel, $sec, null, $t1, $t2, now(), $leaves, $fsExits]);
    } else {
        $given = [];
        for ($i = 1; $i <= 40; $i++) {
            $v = $_POST["q$i"] ?? '';
            if (is_array($v)) $v = implode(',', array_filter(array_map('trim', array_map('strval', $v)), fn($x) => $x !== ''));   // 여러 개 고르는 문항 (체크박스 q21[])
            $given[] = $late ? '' : trim((string)$v);
        }
        $score = grade($given, answer_key($setLabel, $sec));
        attempt_q('INSERT INTO test_attempts (user_id, test_type, test_set, section, answers, score, submitted_at, leave_count, fs_exit_count) VALUES (?,?,?,?,?,?,?,?,?)',
            [$me['id'], $testType, $setLabel, $sec, json_encode($given, JSON_UNESCAPED_UNICODE), $score, now(), $leaves, $fsExits]);
    }
    unset($_SESSION[$sessKey]);
    draft_q("DELETE FROM exam_drafts WHERE $draftWhere", $draftKey);
    header('Location: ' . ($isSingle ? 'done.php' : NEXT_SECTION[$section]));
    exit;
}

// 응시 중인 시험이 있으면 이어서 (다시 로그인·다른 PC 여도 답안·남은 시간·음원 위치 그대로), 없으면 새로 시작
$draft = draft_q("SELECT $draftCols FROM exam_drafts WHERE $draftWhere", $draftKey)->fetch();
if ($draft && time() - draft_last_seen($draft) > DRAFT_IDLE_MAX) {
    draft_q("DELETE FROM exam_drafts WHERE $draftWhere", $draftKey);
    $draft = false;
}
if ($draft) {
    $_SESSION[$sessKey] = (int)$draft['started_at'];
    if ((int)$draft['single_mode']) $isSingle = $_SESSION[$singleKey] = true;   // 다시 로그인해도 Individual Skill Test 그대로
} else {
    $_SESSION[$sessKey] = time();
    draft_q("INSERT INTO exam_drafts (user_id, test_set, section, answers, started_at, updated_at, elapsed, audio_pos, single_mode)
             VALUES (?, ?, ?, '{}', ?, 0, 0, 0, ?)
             ON CONFLICT (user_id, test_set, section) DO NOTHING", array_merge($draftKey, [$_SESSION[$sessKey], $isSingle ? 1 : 0]));
}
$elapsed = $draft ? (int)$draft['elapsed'] : 0;
// ExamLock: 이 응시의 화면 이탈 횟수 (이어서 보면 서버 값부터) · 응시마다 다른 키
$lock = ['count' => $draft ? (int)$draft['leave_count'] : 0, 'fs' => $draft ? (int)$draft['fs_exit_count'] : 0, 'started' => $_SESSION[$sessKey]];
// 브라우저로 넘길 자동 저장 설정 (서버에 저장된 답안·사용 시간·음원 위치가 있으면 이어서 씀)
$autosave = [
    'key' => 'ielts-autosave:' . $me['id'] . ':' . $setName . ':' . $section,
    'started' => $_SESSION[$sessKey],
    'elapsed' => $elapsed,
    'pos' => $draft ? (float)$draft['audio_pos'] : 0,
    'server' => $draft && (int)$draft['updated_at'] > 0
        ? ['answers' => json_decode((string)$draft['answers'], true) ?: [], 't' => (int)$draft['updated_at'] * 1000] : null,
];
// 응시 화면을 다 만든 뒤(문항 바·끌어다 놓기 등) 넣음 → 그 뒤 스크립트에서 examSave (남은 시간·음원 위치) 사용
function autosave_script(string $base, array $autosave): void
{
    echo '<script src="' . $base . 'js/exam-autosave.js"></script>' . "\n"
        . '<script>const examSave = ExamAutosave.init(document.getElementById("exam-form"), '
        . json_encode($autosave, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ');</script>' . "\n";
}
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
function submitNow() { form.submit(); }
// 위 막대 도구: 연결 상태 · Options (제출 / Contrast / Text size) · Notes. 연결이 끊기면 제출을 미뤘다가 다시 연결되면 보냄
const tools = ExamTools.mount({ form: form, top: document.querySelector(".lp-top"), key: <?= json_encode($me['id'] . ':' . $setName . ':' . $section . ':' . $_SESSION[$sessKey]) ?>, beforeSend: function () { examSave.clear(); } });
ListeningParts.init({
  root: document.getElementById("exam-content"),
  header: document.getElementById("part-head"),
  nav: document.getElementById("lp-nav"),
  arrows: document.getElementById("lp-arrows"),
  onSubmit: function () { tools.openSubmission(); }
});
// Enter 로 실수 제출 막기
form.addEventListener("keydown", function (e) { if (e.key === "Enter" && e.target.tagName === "INPUT") e.preventDefault(); });
</script>
<?php autosave_script($base, $autosave); /* 화면을 다 만든 뒤 답안 되살리기 → 아래에서 examSave 사용 */ ?>
<script>
// 전체 화면 [Start Test] 를 누른 뒤 음원 시작
// 음원: 재생기 없이 자동 재생 (멈춤·이동 불가). 끝나면 30초 답안 확인 시간 뒤 자동 제출 → Reading 안내
// 정전 등으로 꺼졌다 다시 열면 저장된 재생 위치부터 이어서 (examSave.pos)
const CHECK_SECONDS = 30;
ExamLock.init({ key: <?= json_encode('listening-' . $setName . '-' . $lock['started']) ?>, count: <?= $lock['count'] ?>, fsCount: <?= $lock['fs'] ?>, form: form, onLeave: function () { examSave.save(); }, title: examSave.resumed ? "Resume Listening Test" : "Listening Test", resumed: examSave.resumed,
// 정전 뒤: [Start from the Beginning] → 저장을 멈추고 서버·이 PC 의 답안·음원 위치를 지운 뒤 새로 열기
onRestart: async function () {
  const fd = new FormData(); fd.append("_restart", "1");
  const res = await fetch(location.href, { method: "POST", body: fd, credentials: "same-origin", cache: "no-store" });
  const j = res.ok ? await res.json().catch(function () { return null; }) : null;
  if (!j || !j.ok) throw new Error("restart failed");   // 실패하면 자동 저장은 그대로 → [Continue Test] 가능
  examSave.stop();   // 닫힐 때의 마지막 저장이 옛 답안을 되살리지 않게
  location.replace(location.pathname);
},
onStart: function () {
ExamAudio.start({
  box: document.getElementById("exam-audio"),
  setNo: <?= $setNo ?>,
  serverUrl: <?= json_encode($audioUrl) ?>,
  resumeAt: examSave.pos(),
  onPos: function (t) { examSave.setPos(t); },
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
<script src="<?= $base ?>js/reading-headings.js"></script>
<script src="<?= $base ?>js/reading-matching.js"></script>
<script src="<?= $base ?>js/exam-tools.js"></script>
<script src="<?= $base ?>js/exam-lock.js"></script>
<script>
const form = document.getElementById("exam-form");
const root = document.getElementById("exam-content");
// 위 막대 도구: 연결 상태 · Options (제출 / Contrast / Text size) · Notes
const tools = ExamTools.mount({ form: form, top: document.querySelector(".lp-top"), key: <?= json_encode($me['id'] . ':' . $setName . ':' . $section . ':' . $_SESSION[$sessKey]) ?>, beforeSend: function () { examSave.clear(); } });
// "List of Headings" 문제 → 제목을 끌어다 놓기 (Part 나누기보다 먼저)
ReadingHeadings.init(root);
// 상자 속 보기(A–F, 낱말 목록)에서 고르는 문제 → 보기를 끌어다 놓기
ReadingMatching.init(root);
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
</script>
<?php autosave_script($base, $autosave); /* 화면을 다 만든 뒤 답안 되살리기 → 아래에서 examSave 사용 */ ?>
<script>
// 60분 = 시험 화면이 켜져 있던 시간만 셈 (정전으로 꺼져 있던 동안은 멈춤). [Start Test] 를 누르면 시작, 0 이 되면 자동 제출
const LIMIT = <?= (int)$limit ?>;
const timer = document.getElementById("timer");
let timeUp = false;
function showTime(used) {
  const r = Math.max(0, Math.ceil(LIMIT - used));
  timer.textContent = String(Math.floor(r / 60)).padStart(2, "0") + ":" + String(r % 60).padStart(2, "0");
  timer.classList.toggle("rd-low", r <= 600);
  if (r <= 0 && !timeUp) { timeUp = true; form.submit(); }
}
showTime(examSave.elapsed());
// 전체 화면 + 다른 프로그램으로 넘어가기 막기/경고
ExamLock.init({ key: <?= json_encode('reading-' . $setName . '-' . $lock['started']) ?>, count: <?= $lock['count'] ?>, fsCount: <?= $lock['fs'] ?>, form: form, onLeave: function () { examSave.save(); }, title: examSave.resumed ? "Resume Reading Test" : "Reading Test", resumed: examSave.resumed, onStart: function () { examSave.startClock(showTime); } });
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
// 위 막대 도구: 연결 상태 · Options (제출 / Contrast / Text size) · Notes
const tools = ExamTools.mount({ form: form, top: document.querySelector(".lp-top"), key: <?= json_encode($me['id'] . ':' . $setName . ':' . $section . ':' . $_SESSION[$sessKey]) ?>, beforeSend: function () { if (writing) writing.clearDraft(); examSave.clear(); } });
const writing = WritingLayout.init({
  root: document.getElementById("exam-content"),
  header: document.getElementById("part-head"),
  nav: document.getElementById("lp-nav"),
  arrows: document.getElementById("lp-arrows"),
  draftKey: <?= json_encode($me['id'] . ':' . $setName . ':' . $_SESSION[$sessKey]) ?>,
  onSubmit: function () { tools.openSubmission(); }
});
</script>
<?php autosave_script($base, $autosave); /* 화면을 다 만든 뒤 답안 되살리기 → 아래에서 examSave 사용 */ ?>
<script>
// 60분 = 시험 화면이 켜져 있던 시간만 셈 (정전으로 꺼져 있던 동안은 멈춤). [Start Test] 를 누르면 시작, 0 이 되면 자동 제출
const LIMIT = <?= (int)$limit ?>;
const timer = document.getElementById("timer");
let timeUp = false;
function showTime(used) {
  const r = Math.max(0, Math.ceil(LIMIT - used));
  timer.textContent = String(Math.floor(r / 60)).padStart(2, "0") + ":" + String(r % 60).padStart(2, "0");
  timer.classList.toggle("wr-low", r <= 600);
  if (r <= 0 && !timeUp) { timeUp = true; form.submit(); }   // 임시 저장은 실제로 보낼 때 지움 (ExamTools beforeSend)
}
showTime(examSave.elapsed());
// 전체 화면 + 다른 프로그램으로 넘어가기 막기/경고
ExamLock.init({ key: <?= json_encode('writing-' . $setName . '-' . $lock['started']) ?>, count: <?= $lock['count'] ?>, fsCount: <?= $lock['fs'] ?>, form: form, onLeave: function () { examSave.save(); }, title: examSave.resumed ? "Resume Writing Test" : "Writing Test", resumed: examSave.resumed, onStart: function () { examSave.startClock(showTime); } });
</script>
</body>
</html>
<?php
    exit;
}

// 문제지(content.html)가 없는 세트용 예비 화면: 이어서 보기 시계가 없으므로 예전처럼 시작 시각 기준
$remaining = max(0, $limit - (time() - $_SESSION[$sessKey]));
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
