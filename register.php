<?php
require_once __DIR__ . '/includes/bootstrap.php';

$error = '';
$success = '';
$v = fn($k) => h($_POST[$k] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = array_map(fn($x) => is_string($x) ? trim($x) : '', $_POST);
    $required = ['id', 'password', 'ename', 'gname', 'birth', 'gender', 'phone', 'course', 'user_type', 'campus'];
    foreach ($required as $k) {
        if (($f[$k] ?? '') === '') { $error = 'Please fill in all required fields.'; break; }
    }
    $me = current_user();
    if (!$error && ($f['user_type'] ?? '') === 'admin' && (!$me || $me['user_type'] !== 'admin')) {
        // 원본은 누구나 Admin 가입 가능(보안 문제) → 관리자 로그인 상태에서만 허용
        $error = 'Admin accounts can only be created by an administrator.';
    }
    if (!$error && !in_array($f['course'], COURSES, true)) $error = 'Invalid course.';
    if (!$error && !in_array($f['campus'], CAMPUSES, true)) $error = 'Invalid campus.';
    if (!$error && q('SELECT 1 FROM users WHERE login_id = ?', [$f['id']])->fetch()) $error = 'This User ID is already taken.';

    $photo = null;
    if (!$error && !empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif'], true) || $_FILES['photo']['size'] > 10 * 1024 * 1024) {
            $error = 'Photo must be PNG, JPG or GIF up to 10MB.';
        } else {
            $photo = 'uploads/' . bin2hex(random_bytes(8)) . '.' . $ext;
            move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__ . '/' . $photo);
        }
    }

    if (!$error) {
        q('INSERT INTO users (login_id, password_hash, ename, gname, birth, gender, phone, course, user_type, campus, photo, created_at)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?)', [
            $f['id'], password_hash($f['password'], PASSWORD_DEFAULT), strtoupper($f['ename']), $f['gname'], $f['birth'],
            $f['gender'], $f['phone'], $f['course'], $f['user_type'] === 'admin' ? 'admin' : 'student', $f['campus'], $photo, now()]);
        log_activity($f['id'], 'Registered', 'Joined ' . $f['campus'] . ' campus');
        header('Location: login.php?registered=1');
        exit;
    }
}
$sel = fn($k, $val) => (($_POST[$k] ?? '') === $val) ? ' selected' : '';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SMEAG Registration</title>
<link rel="stylesheet" href="css/pages/register.inline.css">
</head>
<body>
<div class="floating-elements">
  <div class="floating-shape"></div>
  <div class="floating-shape"></div>
  <div class="floating-shape"></div>
</div>

<div class="register-container">
  <div class="register-header">
    <h1>Join SMEAG</h1>
    <p>Create your account and start your English learning journey</p>
  </div>

  <div class="progress-container">
    <div class="progress-bar"><div class="progress-fill" id="progressFill"></div></div>
    <div class="progress-text" id="progressText">Step 1 of 3: Basic Information</div>
  </div>

<?php if ($error): ?>
  <div class="error"><?= h($error) ?></div>
<?php endif; ?>

  <form method="post" enctype="multipart/form-data" id="registerForm">
    <div class="form-step active" id="step1">
      <h2>Basic Information</h2>
      <div class="form-row">
        <div class="form-group"><label for="id">User ID *</label><input type="text" id="id" name="id" required value="<?= $v('id') ?>"></div>
        <div class="form-group"><label for="password">Password *</label><input type="password" id="password" name="password" required></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label for="ename">English Name *</label><input type="text" id="ename" name="ename" required value="<?= $v('ename') ?>"></div>
        <div class="form-group"><label for="gname">Global Name *</label><input type="text" id="gname" name="gname" required value="<?= $v('gname') ?>"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label for="birth">Birth Date *</label><input type="date" id="birth" name="birth" required value="<?= $v('birth') ?>"></div>
        <div class="form-group"><label for="gender">Gender *</label>
          <select id="gender" name="gender" required>
            <option value="">Select Gender</option>
            <option value="Male"<?= $sel('gender', 'Male') ?>>Male</option>
            <option value="Female"<?= $sel('gender', 'Female') ?>>Female</option>
          </select>
        </div>
      </div>
    </div>

    <div class="form-step" id="step2">
      <h2>Contact &amp; Course Information</h2>
      <div class="form-row">
        <div class="form-group"><label for="phone">Phone Number *</label><input type="tel" id="phone" name="phone" required value="<?= $v('phone') ?>"></div>
        <div class="form-group"><label for="course">Course *</label>
          <select id="course" name="course" required>
            <option value="">Select Course</option>
<?php foreach (COURSES as $c): ?>
            <option value="<?= h($c) ?>"<?= $sel('course', $c) ?>><?= h($c) ?></option>
<?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group"><label for="user_type">User Type *</label>
          <select id="user_type" name="user_type" required>
            <option value="">Select User Type</option>
            <option value="admin"<?= $sel('user_type', 'admin') ?>>Admin</option>
            <option value="student"<?= $sel('user_type', 'student') ?>>Student</option>
          </select>
        </div>
        <div class="form-group"><label for="campus">Campus *</label>
          <select id="campus" name="campus" required>
            <option value="">Select Campus</option>
<?php foreach (CAMPUSES as $c): ?>
            <option value="<?= h($c) ?>"<?= $sel('campus', $c) ?>><?= h($c) ?></option>
<?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <div class="form-step" id="step3">
      <h2>Photo &amp; Final Review</h2>
      <div class="form-group">
        <label>Profile Photo (Optional)</label>
        <div class="file-upload">
          <input type="file" id="photo" name="photo" accept="image/png,image/jpeg,image/gif">
          <div class="file-upload-label">
            <div class="file-upload-icon">📷</div>
            <div class="file-upload-text">Click to upload photo<br><small>PNG, JPG, GIF up to 10MB</small></div>
          </div>
        </div>
        <div class="photo-preview" id="photoPreview"></div>
      </div>
    </div>

    <div class="button-group">
      <button type="button" class="btn btn-secondary" id="prevBtn" onclick="changeStep(-1)" style="display:none">Previous</button>
      <button type="button" class="btn btn-primary" id="nextBtn" onclick="changeStep(1)">Next</button>
      <button type="submit" class="btn btn-primary" id="submitBtn" style="display:none">Create Account</button>
    </div>
  </form>

  <div class="login-link">
    Already have an account? <a href="login.php">Sign in here</a>
  </div>
</div>

<script>
let currentStep = 1;
const totalSteps = 3;
const stepTitles = ['Basic Information', 'Contact & Course Information', 'Photo & Final Review'];

function validateStep(step) {
  const fields = document.querySelectorAll('#step' + step + ' [required]');
  for (const f of fields) {
    if (!f.checkValidity()) { f.reportValidity(); return false; }
  }
  return true;
}

function changeStep(dir) {
  if (dir > 0 && !validateStep(currentStep)) return;
  document.getElementById('step' + currentStep).classList.remove('active');
  currentStep = Math.min(totalSteps, Math.max(1, currentStep + dir));
  document.getElementById('step' + currentStep).classList.add('active');
  document.getElementById('progressFill').style.width = (currentStep / totalSteps * 100) + '%';
  document.getElementById('progressText').textContent = 'Step ' + currentStep + ' of ' + totalSteps + ': ' + stepTitles[currentStep - 1];
  document.getElementById('prevBtn').style.display = currentStep === 1 ? 'none' : '';
  document.getElementById('nextBtn').style.display = currentStep === totalSteps ? 'none' : '';
  document.getElementById('submitBtn').style.display = currentStep === totalSteps ? '' : 'none';
}

document.getElementById('photo').addEventListener('change', function () {
  const preview = document.getElementById('photoPreview');
  preview.innerHTML = '';
  if (this.files && this.files[0]) {
    const img = document.createElement('img');
    img.src = URL.createObjectURL(this.files[0]);
    preview.appendChild(img);
  }
});

document.getElementById('registerForm').addEventListener('submit', function () {
  document.getElementById('submitBtn').classList.add('loading');
});
</script>
</body>
</html>
