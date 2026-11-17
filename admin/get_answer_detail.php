<?php
// Check Answer → Check: 영역별 문항 비교 (ids = get_answer_dates 의 응시 묶음)
require_once __DIR__ . '/../includes/bootstrap.php';
$me = current_user();
if (!$me || $me['user_type'] !== 'admin') json_out(['success' => false], 401);

$ids = array_values(array_filter(array_map('intval', explode(',', $_POST['ids'] ?? ''))));
if (!$ids) json_out(['success' => true, 'sections' => []]);
$attempts = q('SELECT * FROM test_attempts WHERE user_id = ? AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ")
               ORDER BY CASE section WHEN 'LISTENING' THEN 1 WHEN 'READING' THEN 2 ELSE 3 END",
    [(int)($_POST['id'] ?? 0), ...$ids])->fetchAll();

// 시험 화면을 떠난 횟수 (NULL = 기록 전 응시)
function leaves_of(array $a): ?int { return isset($a['leave_count']) ? (int)$a['leave_count'] : null; }
function fs_of(array $a): ?int { return isset($a['fs_exit_count']) ? (int)$a['fs_exit_count'] : null; }

$sections = [];
foreach ($attempts as $a) {
    if ($a['section'] === 'WRITING') {
        $sections[] = ['name' => 'Writing', 'leaves' => leaves_of($a), 'fs' => fs_of($a), 'task1' => $a['writing_task1'], 'task2' => $a['writing_task2']];
        continue;
    }
    $key = answer_key($a['test_set'], $a['section']) ?? [];
    $given = json_decode($a['answers'] ?? '[]', true) ?: [];
    $rows = [];
    for ($i = 0; $i < 40; $i++) {
        $rows[] = ['no' => $i + 1, 'given' => $given[$i] ?? '', 'correct' => $key[$i] ?? '', 'ok' => is_correct($given[$i] ?? '', $key[$i] ?? '')];
    }
    $sections[] = ['name' => ucfirst(strtolower($a['section'])), 'leaves' => leaves_of($a), 'fs' => fs_of($a), 'score' => (int)$a['score'], 'rows' => $rows];
}
json_out(['success' => true, 'sections' => $sections]);
