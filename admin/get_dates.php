<?php
// PDF 패널용 날짜 목록: 그 학생이 해당 코스로 응시했거나 점수가 입력된 날짜
require_once __DIR__ . '/../includes/bootstrap.php';
$me = current_user();
if (!$me || $me['user_type'] !== 'admin') json_out(['success' => false], 401);

$id = (int)($_POST['id'] ?? 0);
$course = $_POST['course'] ?? '';
$dates = q('SELECT DISTINCT substr(submitted_at,1,10) d FROM test_attempts WHERE user_id = ? AND test_type = ?
            UNION SELECT DISTINCT test_date d FROM score_entries WHERE user_id = ? AND test_type = ?
            ORDER BY d DESC', [$id, $course, $id, $course])->fetchAll(PDO::FETCH_COLUMN);
json_out(['success' => true, 'dates' => $dates]);
