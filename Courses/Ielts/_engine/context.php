<?php
// 응시 화면 공통: URL 경로 Courses/Ielts/{academic|general}/{ACADEMIC|GENERAL}{N}/{listening|reading|writing}/파일.php
// → router.php (php -S) 또는 .htaccess (Apache) 가 kind / set / section 을 넘겨줌
require_once __DIR__ . '/../../../includes/bootstrap.php';

$kind = $_GET['kind'] ?? '';
$setName = strtoupper($_GET['set'] ?? '');
$section = $_GET['section'] ?? '';
if (!in_array($kind, ['academic', 'general'], true)
    || !preg_match('/^(ACADEMIC|GENERAL)(\d+)$/', $setName, $m)
    || strtolower($m[1]) !== $kind || (int)$m[2] < 1 || (int)$m[2] > SET_COUNT
    || !in_array($section, ['listening', 'reading', 'writing'], true)) {
    http_response_code(404);
    exit('Test not found');
}
$setLabel = $m[1] . ' ' . (int)$m[2];                 // ACADEMIC 1
$moduleLabel = ucfirst(strtolower($m[1])) . ' ' . (int)$m[2]; // Academic 1 (응시 화면 위 막대 — 감독관이 보기 쉽게)
$testType = 'IELTS ' . $m[1];                         // IELTS ACADEMIC
$base = '../../../../../';                             // 앱 루트까지 (페이지 폴더 기준)
$me = require_login($base);

// 실제 문제지(선택): Courses/Ielts/{kind}/{SET}/{section}/content.html 이 있으면 응시 화면에 표시
$contentFile = APP_ROOT . "/Courses/Ielts/$kind/$setName/$section/content.html";

const SECTION_MINUTES = ['listening' => 30, 'reading' => 60, 'writing' => 60];
const NEXT_SECTION = ['listening' => '../reading/readinginfo.php', 'reading' => '../writing/writinginfo.php', 'writing' => 'done.php'];

// Individual Skill Test (student/skill.php): 한 영역만 응시 → 제출하면 다음 영역으로 가지 않고 끝 화면 (done.php)
//   안내 페이지를 ?single=1 로 열면 켜지고, 그냥 열면(전체 시험) 꺼짐. 응시 화면·끝 화면은 세션 값을 봄
$singleKey = "single_skill_{$setName}_$section";
$isSingle = !empty($_SESSION[$singleKey]);
function set_single_mode(string $key): bool
{
    $on = ($_GET['single'] ?? '') === '1';
    if ($on) $_SESSION[$key] = true;
    else unset($_SESSION[$key]);
    return $on;
}

function tw_head(string $title): void
{
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($title) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<style>
  body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #f3e8ff 0%, #e9d5ff 100%); }
  .fade-in { animation: fadeIn 0.5s ease-in; }
  @keyframes fadeIn { 0% { opacity: 0; } 100% { opacity: 1; } }
  .progress-bar { transition: width 0.3s ease-in-out; }
</style>
</head>
<body class="min-h-screen flex flex-col">
<nav class="bg-gradient-to-r from-purple-600 to-purple-400 text-white p-4 shadow-lg">
  <div class="container mx-auto flex justify-between items-center">
    <div class="main-logo bg-white rounded-lg px-3 py-1 shadow"><img src="../../../../../images/smeag_logo.png" alt="SMEAG GLOBAL EDUCATION" class="h-10 w-auto block"></div>
  </div>
</nav>
<?php
}

function tw_foot(string $script = ''): void
{
    ?>
<footer class="bg-gray-800 text-white text-center p-4">
  <p>&copy; 2025 Test Platform. All rights reserved.</p>
</footer>
<?= $script ?>
</body>
</html>
<?php
}
