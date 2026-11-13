<?php
require_once __DIR__ . '/../includes/layout.php';
require_admin();

$type = in_array($_GET['type'] ?? '', SCORE_TYPES, true) ? $_GET['type'] : 'IELTS ACADEMIC TEST';
$subtabs = SUBTABS[$type];
$subtype = in_array($_GET['subtype'] ?? '', $subtabs, true) ? $_GET['subtype'] : $subtabs[0];
$ename = trim($_GET['ename'] ?? '');
$level = trim($_GET['level'] ?? '');
$course = trim($_GET['course'] ?? '');
$date = trim($_GET['date'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

$dataType = DATA_TYPES[$type] ?? null;
$hasTable = $dataType && $subtype !== 'Speaking';
$section = strtoupper($subtype);

$names = q("SELECT DISTINCT ename FROM users WHERE user_type = 'student' ORDER BY ename")->fetchAll(PDO::FETCH_COLUMN);
$levels = $courses = $dates = $rows = [];
$total = 0;

if ($hasTable) {
    if ($ename !== '') {
        // 이름을 고르면 그 학생이 응시한 값으로 LEVEL / COURSE 채움
        $levels = q('SELECT DISTINCT a.test_set FROM test_attempts a JOIN users u ON u.id = a.user_id
                     WHERE a.test_type = ? AND a.section = ? AND u.ename = ?', [$dataType, $section, $ename])->fetchAll(PDO::FETCH_COLUMN);
        natsort($levels);
        $courses = q('SELECT DISTINCT a.section FROM test_attempts a JOIN users u ON u.id = a.user_id
                      WHERE a.test_type = ? AND a.section = ? AND u.ename = ?', [$dataType, $section, $ename])->fetchAll(PDO::FETCH_COLUMN);
    }
    $dates = q('SELECT DISTINCT substr(submitted_at, 1, 10) d FROM test_attempts WHERE test_type = ? AND section = ? ORDER BY d DESC',
        [$dataType, $section])->fetchAll(PDO::FETCH_COLUMN);

    $where = 'a.test_type = ? AND a.section = ?';
    $params = [$dataType, $section];
    if ($ename !== '') { $where .= ' AND u.ename = ?'; $params[] = $ename; }
    if ($level !== '') { $where .= ' AND a.test_set = ?'; $params[] = $level; }
    if ($course !== '') { $where .= ' AND a.section = ?'; $params[] = $course; }
    if ($date !== '') { $where .= ' AND substr(a.submitted_at, 1, 10) = ?'; $params[] = $date; }

    $total = (int)q("SELECT COUNT(*) FROM test_attempts a JOIN users u ON u.id = a.user_id WHERE $where", $params)->fetchColumn();
    $page = min($page, max(1, (int)ceil($total / $perPage)));
    $rows = q("SELECT u.login_id, u.ename, a.section, a.test_set, a.submitted_at, a.score
               FROM test_attempts a JOIN users u ON u.id = a.user_id WHERE $where
               ORDER BY a.submitted_at DESC, a.id DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage), $params)->fetchAll();
}

$filters = array_filter(['NAME' => $ename, 'LEVEL' => $level, 'COURSE' => $course, 'DATE' => $date]);

admin_head('scores');
topbar('Scores', "You can check students' test score information.");
?>
      <div class="section">
        <div class="test-tabs">
<?php foreach (SCORE_TYPES as $t): ?>
          <form method="get" style="display:inline">
            <input type="hidden" name="type" value="<?= h($t) ?>">
            <button type="submit" class="test-tab<?= $t === $type ? ' active' : '' ?>"><?= h($t) ?></button>
          </form>
<?php endforeach; ?>
        </div>

        <div class="sub-tabs">
<?php foreach ($subtabs as $s): ?>
          <form method="get" style="display:inline">
            <input type="hidden" name="type" value="<?= h($type) ?>">
            <input type="hidden" name="subtype" value="<?= h($s) ?>">
            <button type="submit" class="sub-tab <?= strtolower($s) ?><?= $s === $subtype ? ' active' : '' ?>"><?= h($s) ?></button>
          </form>
<?php endforeach; ?>
        </div>

<?php if (!$hasTable): ?>
<?php no_table_box($type, $subtype); ?>
<?php else: ?>
        <form method="get" class="search-bar" id="searchForm">
          <input type="hidden" name="type" value="<?= h($type) ?>">
          <input type="hidden" name="subtype" value="<?= h($subtype) ?>">
          <select name="ename" onchange="this.form.level.value='';this.form.course.value='';this.form.submit()" style="min-width:360px">
            <option value="">NAME SELECT</option>
<?php foreach ($names as $n): ?>
            <option value="<?= h($n) ?>"<?= $n === $ename ? ' selected' : '' ?>><?= h($n) ?></option>
<?php endforeach; ?>
          </select>
          <select name="level">
            <option value="">LEVEL SELECT</option>
<?php foreach ($levels as $l): ?>
            <option value="<?= h($l) ?>"<?= $l === $level ? ' selected' : '' ?>><?= h($l) ?></option>
<?php endforeach; ?>
          </select>
          <select name="course">
            <option value="">COURSE SELECT</option>
<?php foreach ($courses as $c): ?>
            <option value="<?= h($c) ?>"<?= $c === $course ? ' selected' : '' ?>><?= h($c) ?></option>
<?php endforeach; ?>
          </select>
          <select name="date" onchange="this.form.submit()">
            <option value="">DATE SELECT</option>
<?php foreach ($dates as $d): ?>
            <option value="<?= h($d) ?>"<?= $d === $date ? ' selected' : '' ?>><?= h($d) ?></option>
<?php endforeach; ?>
          </select>
          <button type="submit">SEARCH</button>
          <button type="button" onclick="location.href='?type=<?= urlencode($type) ?>&subtype=<?= urlencode($subtype) ?>'">초기화</button>
        </form>

<?php if ($filters): ?>
        <div class="search-info">
          <strong>검색 조건:</strong>
          <?= h(implode(', ', array_map(fn($k, $v) => "$k: $v", array_keys($filters), $filters))) ?> (총 <?= $total ?>건)
        </div>
<?php endif; ?>

        <table class="scores-table">
          <tr><th>ID</th><th>English Name</th><th>Course</th><th>Level</th><th>Date</th><th>Score</th></tr>
<?php if (!$rows): ?>
          <tr><td colspan="6" class="empty-row">No data found.</td></tr>
<?php endif; ?>
<?php foreach ($rows as $r): ?>
          <tr>
            <td><?= h($r['login_id']) ?></td>
            <td><?= h($r['ename']) ?></td>
            <td><?= h($r['section']) ?></td>
            <td><?= h($r['test_set']) ?></td>
            <td><?= h($r['submitted_at']) ?></td>
            <td><?= (int)$r['score'] ?></td>
          </tr>
<?php endforeach; ?>
        </table>
<?php button_pagination($page, (int)ceil($total / $perPage)); ?>
<?php endif; ?>
      </div>
<?php admin_foot();
