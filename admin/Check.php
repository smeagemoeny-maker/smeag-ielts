<?php
require_once __DIR__ . '/../includes/layout.php';
require_admin();

$types = array_slice(SCORE_TYPES, 0, 6);   // SPEAKING MASTER 없음
$type = in_array($_GET['type'] ?? '', $types, true) ? $_GET['type'] : 'IELTS ACADEMIC TEST';
$subtabs = ['Listening', 'Reading', 'Writing'];
$subtype = in_array($_GET['subtype'] ?? '', $subtabs, true) ? $_GET['subtype'] : 'Listening';
$section = strtoupper($subtype);
$dataType = DATA_TYPES[$type] ?? null;

$ename = trim($_GET['ename'] ?? '');
$level = trim($_GET['level'] ?? '');
$course = trim($_GET['course'] ?? '');
$date = trim($_GET['date'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));

$names = q("SELECT DISTINCT ename FROM users WHERE user_type = 'student' ORDER BY ename")->fetchAll(PDO::FETCH_COLUMN);
$levels = $courses = $dates = [];
$attempt = null;
$total = 0;

if ($dataType) {
    $dateSql = 'SELECT DISTINCT substr(a.submitted_at,1,10) d FROM test_attempts a JOIN users u ON u.id = a.user_id WHERE a.test_type = ? AND a.section = ?';
    $dateParams = [$dataType, $section];
    if ($ename !== '') {
        $levels = q('SELECT DISTINCT a.test_set FROM test_attempts a JOIN users u ON u.id = a.user_id WHERE a.test_type = ? AND a.section = ? AND u.ename = ?',
            [$dataType, $section, $ename])->fetchAll(PDO::FETCH_COLUMN);
        natsort($levels);
        $courses = [$section];
        $dateSql .= ' AND u.ename = ?';
        $dateParams[] = $ename;
    }
    $dates = q($dateSql . ' ORDER BY d DESC', $dateParams)->fetchAll(PDO::FETCH_COLUMN);

    $where = 'a.test_type = ? AND a.section = ?';
    $params = [$dataType, $section];
    if ($ename !== '') { $where .= ' AND u.ename = ?'; $params[] = $ename; }
    if ($level !== '') { $where .= ' AND a.test_set = ?'; $params[] = $level; }
    if ($date !== '') { $where .= ' AND substr(a.submitted_at,1,10) = ?'; $params[] = $date; }
    $total = (int)q("SELECT COUNT(*) FROM test_attempts a JOIN users u ON u.id = a.user_id WHERE $where", $params)->fetchColumn();
    $page = min($page, max(1, $total));
    // 한 페이지 = 응시 1건 (원본은 Next 가 동작하지 않았음)
    $attempt = q("SELECT a.*, u.ename, u.login_id FROM test_attempts a JOIN users u ON u.id = a.user_id WHERE $where
                  ORDER BY a.submitted_at DESC, a.id DESC LIMIT 1 OFFSET " . ($page - 1), $params)->fetch();
}

admin_head('check');
topbar('Test Grading Review', "View students' test grading results.");
?>
      <div class="section">
        <div class="test-tabs" style="flex-wrap:wrap">
<?php foreach ($types as $t): ?>
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
            <button type="submit" class="sub-tab<?= $s === $subtype ? ' active' : '' ?>"><?= h($s) ?></button>
          </form>
<?php endforeach; ?>
        </div>

<?php if (!$dataType): ?>
<?php no_table_box($type, $subtype); ?>
<?php else: ?>
        <form method="get" class="search-bar">
          <input type="hidden" name="type" value="<?= h($type) ?>">
          <input type="hidden" name="subtype" value="<?= h($subtype) ?>">
          <select name="ename" onchange="this.form.level.value='';this.form.date.value='';this.form.submit()" style="min-width:360px">
            <option value="">Select Name</option>
<?php foreach ($names as $n): ?>
            <option value="<?= h($n) ?>"<?= $n === $ename ? ' selected' : '' ?>><?= h($n) ?></option>
<?php endforeach; ?>
          </select>
          <select name="level" onchange="this.form.submit()">
            <option value="">Select Level</option>
<?php foreach ($levels as $l): ?>
            <option value="<?= h($l) ?>"<?= $l === $level ? ' selected' : '' ?>><?= h($l) ?></option>
<?php endforeach; ?>
          </select>
          <select name="course">
            <option value="">Select Course</option>
<?php foreach ($courses as $c): ?>
            <option value="<?= h($c) ?>"<?= $c === $course ? ' selected' : '' ?>><?= h($c) ?></option>
<?php endforeach; ?>
          </select>
          <select name="date" onchange="this.form.submit()">
            <option value="">Select Date</option>
<?php foreach ($dates as $d): ?>
            <option value="<?= h($d) ?>"<?= $d === $date ? ' selected' : '' ?>><?= h($d) ?></option>
<?php endforeach; ?>
          </select>
          <button type="submit">Search</button>
          <button type="button" onclick="location.href='?type=<?= urlencode($type) ?>&subtype=<?= urlencode($subtype) ?>'">Reset</button>
        </form>

<?php if ($attempt): ?>
        <p style="margin:0 0 14px;color:#666">
          <strong style="color:#6c63ff"><?= h($attempt['ename']) ?></strong> (<?= h($attempt['login_id']) ?>) ·
          <?= h($attempt['test_set']) ?> · <?= h($attempt['submitted_at']) ?>
          <?php if ($section !== 'WRITING'): ?> · Score <?= (int)$attempt['score'] ?> / 40<?php endif; ?>
        </p>
<?php endif; ?>

        <table class="scores-table">
          <tr><th>Question</th><th>Your Answer</th><th>Correct Answer</th><th>Result</th></tr>
<?php if (!$attempt): ?>
          <tr><td colspan="4" class="empty-row">No data found.</td></tr>
<?php elseif ($section === 'WRITING'): ?>
<?php foreach ([1 => $attempt['writing_task1'], 2 => $attempt['writing_task2']] as $no => $text): ?>
          <tr>
            <td><?= $no ?></td>
            <td style="width:75%">
              <div class="notebook-answer"><?php foreach (preg_split('/\n\s*\n/', trim((string)$text)) as $para): if (trim($para) === '') continue; ?><p><?= nl2br(h(trim($para))) ?></p><?php endforeach; ?><span class="word-count">Word Count: <?= word_count($text) ?></span></div>
            </td>
            <td>-</td>
            <td><span class="result-incorrect">Incorrect</span></td>
          </tr>
<?php endforeach; ?>
<?php else:
    $key = answer_key($attempt['test_set'], $section) ?? [];
    $given = json_decode($attempt['answers'] ?? '[]', true) ?: [];
    for ($i = 0; $i < 40; $i++):
        $ok = is_correct($given[$i] ?? '', $key[$i] ?? ''); ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td><?= h($given[$i] ?? '') ?></td>
            <td><?= h($key[$i] ?? '') ?></td>
            <td><span class="<?= $ok ? 'result-correct' : 'result-incorrect' ?>"><?= $ok ? 'Correct' : 'Incorrect' ?></span></td>
          </tr>
<?php endfor; endif; ?>
        </table>

<?php if ($total > 0): ?>
        <div class="pagination">
          <button type="button" <?= $page <= 1 ? 'disabled' : '' ?> onclick="location.href='<?= h(qs(['page' => $page - 1])) ?>'">Previous</button>
          <span class="active"><?= $page ?> / <?= $total ?></span>
          <button type="button" <?= $page >= $total ? 'disabled' : '' ?> onclick="location.href='<?= h(qs(['page' => $page + 1])) ?>'">Next</button>
        </div>
<?php endif; ?>
<?php endif; ?>
      </div>
<?php admin_foot();
