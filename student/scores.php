<?php
// 학생 Scores — 관리자 Scores 와 같은 모양 (시험 탭 · 영역 탭 · Level / Date 검색) + 내 통계 · 최근 점수 추이 · 응시 기록
//   본인 응시 기록만 보임. Writing 은 온라인 채점이 없어 "Submitted" 로 표시
//   맨 아래 Test Score Summary: Listening · Reading 영역별 통계 (응시 수 · 평균 · 최고 · 최근) + 세트별 최고 점수
require_once __DIR__ . '/../includes/student_layout.php';
$me = require_student();

$type = in_array($_GET['type'] ?? '', SCORE_TYPES, true) ? $_GET['type'] : 'IELTS ACADEMIC TEST';
$subtabs = SUBTABS[$type];
$subtype = in_array($_GET['subtype'] ?? '', $subtabs, true) ? $_GET['subtype'] : $subtabs[0];
$level = trim($_GET['level'] ?? '');
$date = trim($_GET['date'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

$dataType = DATA_TYPES[$type] ?? null;
$hasData = $dataType && $subtype !== 'Speaking';
$section = strtoupper($subtype);
$isWriting = $section === 'WRITING';

$levels = $dates = $rows = $trend = [];
$total = 0;
$avg = $best = null;

if ($hasData) {
    $base = 'user_id = ? AND test_type = ? AND section = ?';
    $bp = [$me['id'], $dataType, $section];
    $levels = q("SELECT DISTINCT test_set FROM test_attempts WHERE $base", $bp)->fetchAll(PDO::FETCH_COLUMN);
    natsort($levels);
    $dates = q("SELECT DISTINCT substr(submitted_at, 1, 10) d FROM test_attempts WHERE $base ORDER BY d DESC", $bp)->fetchAll(PDO::FETCH_COLUMN);

    $where = $base;
    $params = $bp;
    if ($level !== '') { $where .= ' AND test_set = ?'; $params[] = $level; }
    if ($date !== '') { $where .= ' AND substr(submitted_at, 1, 10) = ?'; $params[] = $date; }

    $stat = q("SELECT COUNT(*) n, AVG(score) a, MAX(score) b FROM test_attempts WHERE $where", $params)->fetch();
    $total = (int)$stat['n'];
    if ($total && !$isWriting) { $avg = round((float)$stat['a'], 1); $best = (int)$stat['b']; }

    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pages);
    $rows = q("SELECT test_set, section, score, submitted_at FROM test_attempts WHERE $where
               ORDER BY submitted_at DESC, id DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage), $params)->fetchAll();
    if (!$isWriting) {
        $trend = array_reverse(q("SELECT test_set, score, submitted_at FROM test_attempts WHERE $where
                                  ORDER BY submitted_at DESC, id DESC LIMIT 10", $params)->fetchAll());
    }
}

// 점수 요약 (Listening · Reading) — 지금 고른 시험 종류의 내 응시 전체 (Level / Date 검색과 상관없이)
$summary = ['LISTENING' => null, 'READING' => null];
$bySet = [];
if ($dataType) {
    foreach (array_keys($summary) as $sec) {
        $st = q('SELECT COUNT(*) n, AVG(score) a, MAX(score) b FROM test_attempts WHERE user_id = ? AND test_type = ? AND section = ?',
            [$me['id'], $dataType, $sec])->fetch();
        if (!(int)$st['n']) continue;
        $last = q('SELECT test_set, score, submitted_at FROM test_attempts WHERE user_id = ? AND test_type = ? AND section = ?
                   ORDER BY submitted_at DESC, id DESC LIMIT 1', [$me['id'], $dataType, $sec])->fetch();
        $summary[$sec] = ['n' => (int)$st['n'], 'avg' => round((float)$st['a'], 1), 'best' => (int)$st['b'], 'last' => $last];
    }
    // 세트별: 영역마다 응시 횟수 · 최고 점수 · 마지막 응시
    foreach (q("SELECT test_set, section, COUNT(*) n, MAX(score) b, MAX(submitted_at) d FROM test_attempts
                WHERE user_id = ? AND test_type = ? AND section IN ('LISTENING', 'READING')
                GROUP BY test_set, section", [$me['id'], $dataType])->fetchAll() as $r) {
        $bySet[$r['test_set']][$r['section']] = ['n' => (int)$r['n'], 'best' => (int)$r['b']];
        $bySet[$r['test_set']]['d'] = max($bySet[$r['test_set']]['d'] ?? '', (string)$r['d']);
    }
    uksort($bySet, 'strnatcasecmp');
}

/** 최근 점수 추이 — 한 줄 꺾은선 (0~40 고정 눈금), 점마다 마우스를 올리면 세트·날짜·점수 */
function trend_chart(array $pts): string
{
    $W = 900; $H = 280; $L = 44; $R = 24; $T = 16; $B = 40;
    $pw = $W - $L - $R; $ph = $H - $T - $B;
    $n = count($pts);
    $x = fn($i) => $L + ($n === 1 ? $pw / 2 : $pw * $i / ($n - 1));
    $y = fn($v) => $T + $ph * (1 - $v / 40);

    $s = '<svg class="tr-svg" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="Score trend of the last ' . $n . ' tests, out of 40">';
    foreach ([0, 10, 20, 30, 40] as $g) {
        $gy = $y($g);
        $s .= '<line x1="' . $L . '" x2="' . ($W - $R) . '" y1="' . $gy . '" y2="' . $gy . '" class="tr-grid' . ($g === 0 ? ' tr-base' : '') . '"/>';
        $s .= '<text x="' . ($L - 8) . '" y="' . ($gy + 4) . '" class="tr-tick" text-anchor="end">' . $g . '</text>';
    }
    $line = [];
    foreach ($pts as $i => $p) $line[] = round($x($i), 1) . ',' . round($y((int)$p['score']), 1);
    if ($n > 1) $s .= '<polyline points="' . implode(' ', $line) . '" class="tr-line"/>';
    foreach ($pts as $i => $p) {
        $cx = round($x($i), 1); $cy = round($y((int)$p['score']), 1);
        $tip = h($p['test_set']) . ' · ' . h(substr((string)$p['submitted_at'], 0, 10)) . ' · <b>' . (int)$p['score'] . ' / 40</b>';
        $s .= '<g class="tr-pt" data-tip="' . h($tip) . '">'
            . '<circle cx="' . $cx . '" cy="' . $cy . '" r="14" class="tr-hit"/>'
            . '<circle cx="' . $cx . '" cy="' . $cy . '" r="5" class="tr-dot"/></g>';
        $s .= '<text x="' . $cx . '" y="' . ($H - $B + 20) . '" class="tr-tick" text-anchor="middle">' . h(substr((string)$p['submitted_at'], 5, 5)) . '</text>';
    }
    // 마지막 점수만 숫자로 표시
    $last = end($pts);
    $s .= '<text x="' . round($x($n - 1), 1) . '" y="' . (round($y((int)$last['score']), 1) - 12) . '" class="tr-label" text-anchor="middle">' . (int)$last['score'] . '</text>';
    return $s . '</svg>';
}

$query = array_filter(['type' => $type, 'subtype' => $subtype, 'level' => $level, 'date' => $date], fn($v) => $v !== '');

student_head('scores', 'Scores - SMEAG');
?>
<link rel="stylesheet" href="../css/pages/scores.inline.css">
<style>
.ms-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 18px; margin-bottom: 24px; }
.ms-stat { background: #fff; border-radius: 14px; box-shadow: 0 2px 12px 0 rgba(80, 80, 160, 0.06); padding: 22px 12px; text-align: center; }
.ms-stat b { display: block; font-size: 2.1rem; color: #6c63ff; line-height: 1.2; font-variant-numeric: tabular-nums; }
.ms-stat b small { font-size: 1rem; color: #9a96c9; font-weight: 600; }
.ms-stat span { color: #666; font-size: .95rem; }
.ms-panel { background: #fff; border-radius: 14px; box-shadow: 0 2px 12px 0 rgba(80, 80, 160, 0.06); padding: 20px 24px; margin-bottom: 24px; position: relative; }
.ms-panel h3 { margin: 0 0 12px; color: #6c63ff; text-align: center; font-size: 1.1rem; }
.ms-note { text-align: center; color: #888; padding: 18px; }
.tr-svg { width: 100%; height: auto; display: block; overflow: visible; }
.tr-grid { stroke: #ecebf5; stroke-width: 1; }
.tr-base { stroke: #c9c6e0; }
.tr-tick { fill: #8a87a8; font-size: 12px; }
.tr-label { fill: #333; font-size: 13px; font-weight: 700; }
.tr-line { fill: none; stroke: #6c63ff; stroke-width: 2; stroke-linejoin: round; stroke-linecap: round; }
.tr-dot { fill: #6c63ff; stroke: #fff; stroke-width: 2; }
.tr-hit { fill: transparent; cursor: pointer; }
.tr-pt:hover .tr-dot { r: 7; }
.tr-tip { position: absolute; pointer-events: none; background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 6px 10px; font-size: 13px; color: #333;
  box-shadow: 0 4px 12px rgba(0,0,0,.12); white-space: nowrap; transform: translate(-50%, calc(-100% - 12px)); display: none; }
.ms-pages { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px; margin-top: 18px; }
.sm-cards { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; margin-bottom: 18px; }
.sm-card { border: 1px solid #ecebf5; border-radius: 12px; padding: 16px 18px; }
.sm-head { font-weight: 700; color: #6c63ff; margin-bottom: 12px; }
.sm-vals { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; text-align: center; }
.sm-vals b { display: block; font-size: 1.5rem; color: #333; line-height: 1.2; font-variant-numeric: tabular-nums; }
.sm-vals b small { font-size: .85rem; color: #9a96c9; font-weight: 600; }
.sm-vals span { color: #777; font-size: .85rem; }
.sm-last { margin-top: 12px; color: #777; font-size: .9rem; text-align: center; }
.sm-none { color: #888; padding: 14px 0; text-align: center; }
.sm-n { color: #999; font-size: .85rem; }
@media (max-width: 900px) { .sm-cards { grid-template-columns: 1fr; } }
.ms-pages a, .ms-pages span { padding: 7px 13px; border-radius: 6px; background: #f3f0ff; color: #6c63ff; font-weight: 600; text-decoration: none; }
.ms-pages .active { background: #6c63ff; color: #fff; }
@media (max-width: 900px) { .ms-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>
      <div class="etest-title">My Test Scores</div>
      <div class="etest-desc">Track your progress and performance · <?= h($me['ename']) ?> (<?= h($me['login_id']) ?>)</div>

      <div class="test-tabs">
<?php foreach (SCORE_TYPES as $t): ?>
        <a class="test-tab<?= $t === $type ? ' active' : '' ?>" style="text-decoration:none" href="?<?= h(http_build_query(['type' => $t])) ?>"><?= h($t) ?></a>
<?php endforeach; ?>
      </div>

      <div class="sub-tabs">
<?php foreach ($subtabs as $s): ?>
        <a class="sub-tab <?= strtolower($s) ?><?= $s === $subtype ? ' active' : '' ?>" style="text-decoration:none" href="?<?= h(http_build_query(['type' => $type, 'subtype' => $s])) ?>"><?= h($s) ?></a>
<?php endforeach; ?>
      </div>

<?php if (!$hasData): ?>
      <div class="ms-panel ms-note"><i class="fa-solid fa-circle-info"></i> Online test results for <?= h($type) ?> <?= h($subtype) ?> are not available yet.</div>
<?php else: ?>
      <form method="get" class="search-bar">
        <input type="hidden" name="type" value="<?= h($type) ?>">
        <input type="hidden" name="subtype" value="<?= h($subtype) ?>">
        <select name="level">
          <option value="">All levels</option>
<?php foreach ($levels as $l): ?>
          <option value="<?= h($l) ?>"<?= $l === $level ? ' selected' : '' ?>><?= h($l) ?></option>
<?php endforeach; ?>
        </select>
        <select name="date">
          <option value="">All dates</option>
<?php foreach ($dates as $d): ?>
          <option value="<?= h($d) ?>"<?= $d === $date ? ' selected' : '' ?>><?= h($d) ?></option>
<?php endforeach; ?>
        </select>
        <button type="submit">Search</button>
        <button type="button" onclick="location.href='?<?= h(http_build_query(['type' => $type, 'subtype' => $subtype])) ?>'">Reset</button>
      </form>

      <div class="ms-stats">
        <div class="ms-stat"><b><?= $total ?></b><span>Total Tests Taken</span></div>
        <div class="ms-stat"><b><?= $avg === null ? '—' : $avg . ' <small>/ 40</small>' ?></b><span>Average Score</span></div>
        <div class="ms-stat"><b><?= $best === null ? '—' : $best . ' <small>/ 40</small>' ?></b><span>Highest Score</span></div>
        <div class="ms-stat"><b><?= h($subtype) ?></b><span>Current Section</span></div>
      </div>

      <div class="ms-panel">
        <h3>Recent Performance Trend</h3>
<?php if ($isWriting): ?>
        <div class="ms-note">Writing is marked by your teacher, so no online score is shown here.</div>
<?php elseif (!$trend): ?>
        <div class="ms-note">No <?= h(strtolower($subtype)) ?> tests yet.</div>
<?php else: ?>
        <?= trend_chart($trend) ?>
        <div class="tr-tip" id="trTip"></div>
<?php endif; ?>
      </div>

      <div class="ms-panel">
        <h3>My <?= h($subtype) ?> Test History</h3>
        <table class="scores-table">
          <tr><th>Date</th><th>Level</th><th>Section</th><th>Score</th></tr>
<?php if (!$rows): ?>
          <tr><td colspan="4" class="student-empty">No tests found.</td></tr>
<?php endif; ?>
<?php foreach ($rows as $r): ?>
          <tr>
            <td><?= h($r['submitted_at']) ?></td>
            <td><?= h($r['test_set']) ?></td>
            <td><?= h(ucfirst(strtolower($r['section']))) ?></td>
            <td><?= $isWriting ? 'Submitted' : (int)$r['score'] . ' / 40' ?></td>
          </tr>
<?php endforeach; ?>
        </table>
<?php if ($pages > 1): ?>
        <div class="ms-pages">
<?php for ($p = 1; $p <= $pages; $p++): ?>
<?php if ($p === $page): ?>
          <span class="active"><?= $p ?></span>
<?php else: ?>
          <a href="?<?= h(http_build_query($query + ['page' => $p])) ?>"><?= $p ?></a>
<?php endif; ?>
<?php endfor; ?>
        </div>
<?php endif; ?>
      </div>
<?php endif; ?>

      <div class="check-panel">
        <div class="check-panel-head"><h3>Test Score Summary</h3></div>
<?php if (!$dataType): ?>
        <div class="student-empty">Online test results for <?= h($type) ?> are not available yet.</div>
<?php elseif (!$summary['LISTENING'] && !$summary['READING']): ?>
        <div class="student-empty">No listening or reading tests yet.</div>
<?php else: ?>
        <div class="sm-cards">
<?php foreach ($summary as $sec => $s): ?>
          <div class="sm-card">
            <div class="sm-head"><i class="fa-solid <?= $sec === 'LISTENING' ? 'fa-headphones' : 'fa-book-open' ?>"></i> <?= ucfirst(strtolower($sec)) ?></div>
<?php if (!$s): ?>
            <div class="sm-none">No <?= strtolower($sec) ?> tests yet.</div>
<?php else: ?>
            <div class="sm-vals">
              <div><b><?= $s['n'] ?></b><span>Tests</span></div>
              <div><b><?= $s['avg'] ?><small> / 40</small></b><span>Average</span></div>
              <div><b><?= $s['best'] ?><small> / 40</small></b><span>Highest</span></div>
              <div><b><?= (int)$s['last']['score'] ?><small> / 40</small></b><span>Latest</span></div>
            </div>
            <div class="sm-last">Latest: <?= h($s['last']['test_set']) ?> · <?= h(substr((string)$s['last']['submitted_at'], 0, 10)) ?></div>
<?php endif; ?>
          </div>
<?php endforeach; ?>
        </div>
        <table class="scores-table sm-table">
          <tr><th>Level</th><th>Listening (best)</th><th>Reading (best)</th><th>Last taken</th></tr>
<?php foreach ($bySet as $set => $r): ?>
          <tr>
            <td><?= h($set) ?></td>
<?php foreach (['LISTENING', 'READING'] as $sec): ?>
            <td><?= isset($r[$sec]) ? '<b>' . $r[$sec]['best'] . '</b> / 40 <span class="sm-n">(' . $r[$sec]['n'] . ' ' . ($r[$sec]['n'] === 1 ? 'try' : 'tries') . ')</span>' : '<span class="sm-n">—</span>' ?></td>
<?php endforeach; ?>
            <td><?= h(substr($r['d'], 0, 10)) ?></td>
          </tr>
<?php endforeach; ?>
        </table>
<?php endif; ?>
      </div>
<?php
student_foot('<script>
(function () {
  const tip = document.getElementById("trTip"); if (!tip) return;
  const box = tip.parentElement;
  document.querySelectorAll(".tr-pt").forEach(function (g) {
    g.addEventListener("mouseenter", function () {
      const d = g.querySelector(".tr-dot").getBoundingClientRect(), b = box.getBoundingClientRect();
      tip.innerHTML = g.dataset.tip;
      tip.style.left = (d.left + d.width / 2 - b.left) + "px";
      tip.style.top = (d.top - b.top) + "px";
      tip.style.display = "block";
    });
    g.addEventListener("mouseleave", function () { tip.style.display = "none"; });
  });
})();
</script>');
