<?php
// INFO 버튼 (원본은 500 + 빈 응답이었음)
require_once __DIR__ . '/../includes/bootstrap.php';
$me = current_user();
if (!$me || $me['user_type'] !== 'admin') json_out(['success' => false, 'message' => 'Unauthorized'], 401);

$id = (int)($_GET['id'] ?? 0);
$st = $id ? q("SELECT * FROM users WHERE id = ?", [$id]) : q('SELECT * FROM users WHERE ename = ? LIMIT 1', [$_GET['ename'] ?? '']);
$u = $st->fetch();
if (!$u) json_out(['success' => false, 'message' => 'Student not found'], 404);
unset($u['password_hash']);

json_out([
    'success' => true,
    'student' => $u,
    'attempts' => (int)q('SELECT COUNT(*) FROM test_attempts WHERE user_id = ?', [$u['id']])->fetchColumn(),
    'latest_score' => q('SELECT * FROM score_entries WHERE user_id = ? ORDER BY test_date DESC, id DESC LIMIT 1', [$u['id']])->fetch() ?: null,
]);
