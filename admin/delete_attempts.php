<?php
// Check Answer 패널: 응시 기록 1회(세트 묶음) 삭제 — 그 학생의 test_attempts 행만 지움
require_once __DIR__ . '/../includes/bootstrap.php';
$me = current_user();
if (!$me || $me['user_type'] !== 'admin') json_out(['success' => false], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['success' => false], 405);

$userId = (int)($_POST['id'] ?? 0);
$ids = array_values(array_filter(array_map('intval', explode(',', (string)($_POST['ids'] ?? '')))));
if (!$userId || !$ids) json_out(['success' => false, 'error' => 'Nothing to delete.'], 400);

$marks = implode(',', array_fill(0, count($ids), '?'));
$n = q("DELETE FROM test_attempts WHERE user_id = ? AND id IN ($marks)", array_merge([$userId], $ids))->rowCount();
json_out(['success' => true, 'deleted' => $n]);
