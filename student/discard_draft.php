<?php
// "Resume Test" 옆 Discard — 응시 중인(제출 전) 시험 기록을 지움. DRAFT_DISCARD_USERS 계정만 (includes/exam_drafts.php)
require_once __DIR__ . '/../includes/student_layout.php';
$me = require_student();

$back = 'index.php';
$ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
if (preg_match('~/student/(index|etest|skill)\.php~', $ref, $m)) $back = $m[1] . '.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && can_discard_drafts($me)) {
    $set = strtoupper((string)($_POST['set'] ?? ''));
    $section = (string)($_POST['section'] ?? '');
    if (preg_match('/^(ACADEMIC|GENERAL)\d+$/', $set) && in_array($section, ['listening', 'reading', 'writing'], true)) {
        draft_q('DELETE FROM exam_drafts WHERE user_id = ? AND test_set = ? AND section = ?', [(int)$me['id'], $set, $section]);
    }
}
header('Location: ' . $back);
exit;
