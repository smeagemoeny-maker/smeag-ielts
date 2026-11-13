<?php
require_once __DIR__ . '/includes/bootstrap.php';
$u = current_user();
if (!$u) header('Location: login.php');
elseif ($u['user_type'] === 'admin') header('Location: admin/admin_page.php');
else header('Location: student/index.php');
