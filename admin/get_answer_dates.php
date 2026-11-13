<?php
// Check Answer 패널: 응시 기록. 같은 세트를 4시간 안에 연달아 본 영역들을 1회로 묶음 (자정 넘김 대응)
require_once __DIR__ . '/../includes/bootstrap.php';
$me = current_user();
if (!$me || $me['user_type'] !== 'admin') json_out(['success' => false], 401);

$rows = q('SELECT id, test_set, test_type, section, submitted_at FROM test_attempts WHERE user_id = ? ORDER BY submitted_at, id',
    [(int)($_POST['id'] ?? 0)])->fetchAll();

$sessions = [];
$open = [];   // test_set => index in $sessions
foreach ($rows as $r) {
    $t = strtotime($r['submitted_at']);
    $i = $open[$r['test_set']] ?? null;
    if ($i === null || $t - $sessions[$i]['_last'] > 4 * 3600 || in_array($r['section'], $sessions[$i]['_secs'], true)) {
        $sessions[] = ['date' => $r['submitted_at'], 'test_set' => $r['test_set'], 'test_type' => $r['test_type'], 'ids' => [], '_secs' => [], '_last' => $t];
        $i = $open[$r['test_set']] = count($sessions) - 1;
    }
    $sessions[$i]['ids'][] = (int)$r['id'];
    $sessions[$i]['_secs'][] = $r['section'];
    $sessions[$i]['_last'] = $t;
}

$order = ['LISTENING' => 1, 'READING' => 2, 'WRITING' => 3];
foreach ($sessions as &$s) {
    usort($s['_secs'], fn($a, $b) => ($order[$a] ?? 9) <=> ($order[$b] ?? 9));
    $s['subjects'] = implode(', ', array_map(fn($x) => ucfirst(strtolower($x)), $s['_secs']));
    unset($s['_secs'], $s['_last']);
}
json_out(['success' => true, 'sessions' => array_reverse($sessions)]);
