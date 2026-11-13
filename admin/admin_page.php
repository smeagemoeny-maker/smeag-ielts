<?php
require_once __DIR__ . '/../includes/layout.php';
$me = require_admin();

$students = (int)q("SELECT COUNT(*) FROM users WHERE user_type = 'student'")->fetchColumn();
$admins = (int)q("SELECT COUNT(*) FROM users WHERE user_type = 'admin'")->fetchColumn();
$total = (int)q('SELECT COUNT(*) FROM users')->fetchColumn();

// 지난달 대비 증감 (원본은 고정값이었음)
function growth(string $where): array
{
    $thisMonth = date('Y-m-01');
    $lastMonth = date('Y-m-01', strtotime('-1 month'));
    $before = (int)q("SELECT COUNT(*) FROM users WHERE $where created_at < ?", [$lastMonth])->fetchColumn();
    $until = (int)q("SELECT COUNT(*) FROM users WHERE $where created_at < ?", [$thisMonth])->fetchColumn();
    $now = (int)q("SELECT COUNT(*) FROM users WHERE $where 1=1")->fetchColumn();
    $prevGain = $until - $before;
    $gain = $now - $until;
    $pct = $prevGain > 0 ? ($gain - $prevGain) / $prevGain * 100 : ($gain > 0 ? 100 : 0);
    return [$pct >= 0, abs(round($pct, 1))];
}
$cards = [
    ['Total Students', $students, growth("user_type = 'student' AND")],
    ['Total Admins', $admins, growth("user_type = 'admin' AND")],
    ['Total Users', $total, growth('')],
];
$activity = q('SELECT * FROM activity_log ORDER BY created_at DESC, id DESC LIMIT 5')->fetchAll();

admin_head('admin_page');
topbar('Welcome back, ' . $me['login_id'] . '!', 'It is the best time to manage your academy.');
?>
      <div class="card-row">
<?php foreach ($cards as [$title, $value, [$up, $pct]]): ?>
        <div class="card">
          <div class="card-title"><?= h($title) ?></div>
          <div class="card-value"><?= $value ?></div>
          <div class="card-desc"><span class="<?= $up ? 'up' : 'down' ?>"><?= $up ? '↑' : '↓' ?> <?= $pct ?>%</span> vs last month</div>
        </div>
<?php endforeach; ?>
      </div>

      <div class="section">
        <h3>Recent Activity</h3>
        <table class="activity-table">
          <tr><th>Date</th><th>User</th><th>Action</th><th>Details</th></tr>
<?php foreach ($activity as $a): ?>
          <tr><td><?= h(substr($a['created_at'], 0, 10)) ?></td><td><?= h($a['user_label']) ?></td><td><?= h($a['action']) ?></td><td><?= h($a['details']) ?></td></tr>
<?php endforeach; ?>
        </table>
      </div>
<?php admin_foot();
