<?php
require_once __DIR__ . '/includes/bootstrap.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = trim($_POST['id'] ?? '');
    $pw = (string)($_POST['pw'] ?? '');
    $u = q('SELECT * FROM users WHERE lower(login_id) = lower(?)', [$id])->fetch();
    if ($u && password_verify($pw, $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$u['id'];
        header('Location: ' . ($u['user_type'] === 'admin' ? 'admin/admin_page.php' : 'student/index.php'));
        exit;
    }
    $error = 'Invalid User ID or Password.';
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SMEAG Login</title>
<link rel="stylesheet" href="css/pages/login.inline.css">
</head>
<body>
<div class="floating-elements"></div>
<div class="login-container">
  <div class="logo">
    <h1>SMEAG</h1>
    <p>Welcome back! Please sign in to your account.</p>
  </div>
<?php if ($error): ?>
  <div class="error"><?= h($error) ?></div>
<?php endif; ?>
  <form method="post" id="loginForm">
    <div class="form-group">
      <label for="id">User ID</label>
      <input type="text" id="id" name="id" required value="<?= h($_POST['id'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label for="pw">Password</label>
      <input type="password" id="pw" name="pw" required>
    </div>
    <button type="submit" class="login-btn" id="loginBtn">Sign In</button>
  </form>
  <div class="register-link">
    Don't have an account? <a href="register.php">Create one here</a>
  </div>
</div>
<script>
document.getElementById('loginForm').addEventListener('submit', function () {
  document.getElementById('loginBtn').classList.add('loading');
});
</script>
</body>
</html>
