<?php
// 공개 회원가입은 닫음 — 계정은 관리자가 Admin > New Account (admin/accounts.php) 에서 만듦
require_once __DIR__ . '/includes/bootstrap.php';

$me = current_user();
header('Location: ' . ($me && $me['user_type'] === 'admin' ? 'admin/accounts.php' : 'login.php'));
exit;
