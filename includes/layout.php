<?php
// 관리자 공통 레이아웃 (sidebar + main-content + topbar). 원본 DOM 클래스 그대로.
require_once __DIR__ . '/bootstrap.php';

const MENU = [
    'admin_page' => ['admin_page.php', 'fa-solid fa-table-columns', 'Home'],
    'accounts'   => ['accounts.php', 'fa-solid fa-user-plus', 'New Account'],
    'etest'      => ['etest.php', 'fa-solid fa-book', 'English TEST'],
    'scores'     => ['scores.php', 'fa-solid fa-chart-bar', 'Scores'],
    'answer'     => ['answer.php', 'fa-solid fa-key', 'Answer'],
    'indata'     => ['indata.php', 'fa-solid fa-pen-to-square', 'indata'],
    'check'      => ['Check.php', 'fa-solid fa-clipboard-check', 'Test Check'],
    'schedule'   => ['schedule.php', 'fa-solid fa-calendar', 'Schedule'],
    'settings'   => ['#', 'fa-solid fa-gear', 'Settings'],
];

/** $page: 메뉴 키 = css/pages/{page}.inline.css 파일 이름 */
function admin_head(string $page, string $title = 'Admin Dashboard'): void
{
    ?><!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($title) ?></title>
<link rel="stylesheet" href="../css/panel-style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="../css/common.css">
<link rel="stylesheet" href="../css/pages/<?= h($page) ?>.inline.css">
</head>
<body>
<div class="dashboard-container">
  <nav class="sidebar">
    <div class="logo"><img src="../images/smeag_logo.png" alt="SMEAG GLOBAL EDUCATION"></div>
    <ul>
<?php foreach (MENU as $key => [$href, $icon, $label]): ?>
      <li><a href="<?= $href ?>"<?= $key === $page ? ' class="active"' : '' ?>><i class="<?= $icon ?>"></i> <?= $label ?></a></li>
<?php endforeach; ?>
      <li><a href="../logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> LOGOUT</a></li>
    </ul>
  </nav>

  <main class="main-content">
    <div>
<?php
}

function topbar(string $title, string $sub, bool $showUser = true): void
{
    $u = current_user();
    ?>
      <div class="topbar">
        <div class="welcome">
          <?= h($title) ?>
          <span class="sub"><?= h($sub) ?></span>
        </div>
<?php if ($showUser && $u): ?>
        <div class="user-info">
          <div class="avatar"><?= h(mb_strtoupper(mb_substr($u['login_id'], 0, 2))) ?></div>
          <div>
            <div class="name"><?= h($u['login_id']) ?></div>
            <div class="email"><?= h($u['ename']) ?></div>
          </div>
          <a href="#"><i class="fa-regular fa-bell"></i></a>
          <a href="#"><i class="fa-solid fa-magnifying-glass"></i></a>
        </div>
<?php endif; ?>
      </div>
<?php
}

function admin_foot(string $extra = ''): void
{
    echo "    </div>\n  </main>\n</div>\n" . $extra . "\n</body>\n</html>\n";
}

/** Scores / indata 스타일 페이지네이션 (PREV · 10개 블록 · NEXT, button) */
function button_pagination(int $page, int $totalPages): void
{
    if ($totalPages <= 1) return;
    $start = intdiv($page - 1, 10) * 10 + 1;
    $end = min($start + 9, $totalPages);
    echo '<div class="pagination">';
    printf('<button type="button" %s onclick="location.href=\'%s\'">PREV</button>',
        $page <= 1 ? 'disabled' : '', h(qs(['page' => $page - 1])));
    for ($i = $start; $i <= $end; $i++) {
        printf('<button type="button" class="%s" onclick="location.href=\'%s\'">%d</button>',
            $i === $page ? 'active' : '', h(qs(['page' => $i])), $i);
    }
    printf('<button type="button" %s onclick="location.href=\'%s\'">NEXT</button>',
        $page >= $totalPages ? 'disabled' : '', h(qs(['page' => $page + 1])));
    echo '</div>';
}

function no_table_box(string $testName, string $section): void
{
    ?>
    <div class="no-table">
      <i class="fa-solid fa-database"></i>
      <h3>Database table not found</h3>
      <p>The table for <?= h($testName) ?> <?= h($section) ?> is not yet created.</p>
      <p>Please contact the administrator to set up the database structure.</p>
    </div>
    <?php
}
