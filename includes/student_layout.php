<?php
// 학생 공통 레이아웃 — 관리자 화면과 같은 sidebar 디자인 (Home · English TEST · Schedule · Scores · LOGOUT)
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/exam_drafts.php';

const STUDENT_MENU = [
    'home'     => ['index.php', 'fa-solid fa-table-columns', 'Home'],
    'etest'    => ['etest.php', 'fa-solid fa-book', 'English TEST'],
    'skill'    => ['skill.php', 'fa-solid fa-bullseye', 'Individual Skill Test'],
    'schedule' => ['schedule.php', 'fa-solid fa-chart-bar', 'Schedule'],
    'scores'   => ['scores.php', 'fa-solid fa-graduation-cap', 'Scores'],
];

/** 학생 로그인 확인 (관리자는 관리자 화면으로) */
function require_student(): array
{
    $me = require_login('../');
    if ($me['user_type'] === 'admin') {
        header('Location: ../admin/admin_page.php');
        exit;
    }
    return $me;
}

function student_head(string $page, string $title = 'SMEAG'): void
{
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($title) ?></title>
<link rel="stylesheet" href="../css/panel-style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="../css/common.css">
<link rel="stylesheet" href="../css/pages/etest.inline.css">
<style>
.etest-none-wrap { background: #fff; border-radius: 16px; box-shadow: 0 2px 12px 0 rgba(80, 80, 160, 0.06); padding: 28px 24px; margin: 0 auto; max-width: 700px; text-align: center; color: #888; font-size: 1.05rem; }
.student-empty { text-align: center; color: #aaa; padding: 18px; }
.score-card { border: 1px solid #ece9ff; border-radius: 10px; padding: 14px 16px; margin-bottom: 12px; }
.score-card .head { font-weight: 700; color: #333; margin-bottom: 6px; }
.score-card .vals span { display: inline-block; margin-right: 16px; color: #555; }
.score-card .comment { margin: 8px 0 0; color: #666; white-space: pre-line; }
.resume-box { max-width: 700px; margin: 0 auto 28px; background: #fff7ed; border: 2px solid #fb923c; border-radius: 16px; padding: 18px 22px; }
.resume-box h3 { margin: 0 0 6px; color: #c2410c; font-size: 1.15rem; }
.resume-box p { margin: 0 0 12px; color: #555; }
.resume-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 8px 0; border-top: 1px solid #fed7aa; }
.resume-row b { color: #333; }
.resume-btn { background: #ea580c; color: #fff; text-decoration: none; font-weight: 700; padding: 10px 20px; border-radius: 8px; white-space: nowrap; }
.resume-btn:hover { background: #c2410c; }
.student-id-bar { display: flex; align-items: center; justify-content: flex-end; gap: 12px; margin: 0 0 18px; }
.student-id-bar .sid-card { display: flex; align-items: center; gap: 14px; background: #fff; border-radius: 14px; box-shadow: 0 2px 12px 0 rgba(80, 80, 160, 0.08); padding: 12px 22px; }
.student-id-bar .sid-icon { width: 48px; height: 48px; border-radius: 50%; background: #6c63ff; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; }
.student-id-bar .sid-name { font-weight: 700; color: #333; font-size: 1.3rem; line-height: 1.25; }
.student-id-bar .sid-id { color: #6c63ff; font-size: 1.08rem; font-weight: 600; }
</style>
</head>
<body>
<div class="dashboard-container">
  <nav class="sidebar">
    <div class="logo"><img src="../images/smeag_logo.png" alt="SMEAG GLOBAL EDUCATION"></div>
    <ul>
<?php foreach (STUDENT_MENU as $key => [$href, $icon, $label]): ?>
      <li><a href="<?= $href ?>"<?= $key === $page ? ' class="active"' : '' ?>><i class="<?= $icon ?>"></i> <?= $label ?></a></li>
<?php endforeach; ?>
      <li><a href="../logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> LOGOUT</a></li>
    </ul>
  </nav>

  <main class="main-content">
    <div>
<?php $sidMe = current_user(); if ($sidMe): ?>
      <div class="student-id-bar"><div class="sid-card">
        <div class="sid-icon"><i class="fa-solid fa-user"></i></div>
        <div><div class="sid-name"><?= h($sidMe['ename']) ?></div><div class="sid-id">ID: <?= h($sidMe['login_id']) ?></div></div>
      </div></div>
<?php endif; ?>
<?php
}

function student_foot(string $extra = ''): void
{
    echo "    </div>\n  </main>\n</div>\n" . $extra . "\n</body>\n</html>\n";
}

/** 정전 등으로 끊긴(제출 전) 시험 → "Resume Test" 버튼. 답안·남은 시간·음원 위치 그대로 이어서 */
function student_resume_box(array $me): void
{
    $drafts = active_drafts((int)$me['id']);
    if (!$drafts) return;
    ?>
      <div class="resume-box">
        <h3><i class="fa-solid fa-rotate-right"></i> Test in progress</h3>
        <p>Your test was interrupted. Your answers and remaining time are saved. Click <b>Resume Test</b> to continue from where you stopped.</p>
<?php foreach ($drafts as $d): ?>
        <div class="resume-row"><b><?= h($d['label']) ?></b><a class="resume-btn" href="../<?= h($d['url']) ?>">Resume Test</a></div>
<?php endforeach; ?>
      </div>
<?php
}

/** English TEST Courses 카드 + IELTS 세트 목록 (Home · English TEST 공통) */
function student_courses(array $me): void
{
    student_resume_box($me);
    $cards = [
        ['ESL TEST', 'fa-solid fa-book-open', ''],
        ['TOEIC TEST', 'fa-solid fa-book-open', ''],
        ['TOEFL TEST', 'fa-solid fa-book-open', ''],
        ['IELTS ACADEMIC TEST', 'fa-solid fa-book-open', 'academic'],
        ['IELTS GENERAL TEST', 'fa-solid fa-book-open', 'general'],
        ['BUSINESS TEST', 'fa-solid fa-book-open', ''],
        ['SPEAKING MASTER TEST', 'fa-solid fa-microphone', ''],
    ];
    // 관리자 English TEST 화면과 같게: 처음에는 아무 카드도 선택하지 않음
    $open = '';
    ?>
      <div class="etest-title">English TEST Courses</div>
      <div class="etest-desc">Select a test course to manage or view details. Click on IELTS ACADEMIC TEST or IELTS GENERAL TEST to see available test sets.</div>

      <div class="etest-courses">
<?php foreach ($cards as [$name, $icon, $kind]): ?>
        <div class="etest-card<?= $kind !== '' && $kind === $open ? ' selected' : '' ?>" data-kind="<?= $kind ?>" onclick="selectCourse(this)">
          <i class="<?= $icon ?>"></i>
          <?= h($name) ?>
        </div>
<?php endforeach; ?>
      </div>

<?php foreach (['academic' => 'ACADEMIC', 'general' => 'GENERAL'] as $kind => $prefix): ?>
      <div class="etest-<?= $kind ?>-wrap" id="wrap-<?= $kind ?>" style="display:<?= $kind === $open ? 'block' : 'none' ?>">
        <h3 style="text-align:center;color:#6c63ff;margin:0 0 10px;">Available <?= $prefix ?> Test Sets</h3>
        <div class="etest-<?= $kind ?>-list">
<?php for ($n = 1; $n <= SET_COUNT; $n++): ?>
          <button type="button" class="<?= $kind ?>-btn"
            onclick="location.href='../Courses/Ielts/<?= $kind ?>/<?= $prefix . $n ?>/listening/listninginfo.php'"><?= $prefix ?> <?= $n ?></button>
<?php endfor; ?>
        </div>
      </div>
<?php endforeach; ?>
      <div class="etest-none-wrap" id="wrap-none" style="display:none">
        <i class="fa-solid fa-circle-info"></i> Online tests for this course are not available yet.
      </div>
<script>
function selectCourse(card) {
  document.querySelectorAll('.etest-card').forEach(c => c.classList.remove('selected'));
  card.classList.add('selected');
  const kind = card.dataset.kind;
  document.getElementById('wrap-academic').style.display = kind === 'academic' ? 'block' : 'none';
  document.getElementById('wrap-general').style.display = kind === 'general' ? 'block' : 'none';
  document.getElementById('wrap-none').style.display = kind === '' ? 'block' : 'none';
}
</script>
<?php
}
