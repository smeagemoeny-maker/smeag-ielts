<?php
// 새 계정 만들기 (관리자 전용) — 로그인 화면의 "Create one here" 대신 여기서 학생·관리자 ID 를 만듦
require_once __DIR__ . '/../includes/layout.php';
$me = require_admin();

$message = null;
$keep = [];   // 오류 시 입력값 유지
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = array_map(fn($x) => is_string($x) ? trim($x) : '', $_POST);
    $error = '';
    foreach (['id', 'password', 'ename', 'gname', 'birth', 'gender', 'phone', 'course', 'user_type', 'campus'] as $k) {
        if (($f[$k] ?? '') === '') { $error = 'Please fill in all required fields.'; break; }
    }
    if (!$error && !in_array($f['course'], COURSES, true)) $error = 'Invalid course.';
    if (!$error && !in_array($f['campus'], CAMPUSES, true)) $error = 'Invalid campus.';
    if (!$error && !in_array($f['gender'], ['Male', 'Female'], true)) $error = 'Invalid gender.';
    if (!$error && !in_array($f['user_type'], ['student', 'admin'], true)) $error = 'Invalid user type.';
    if (!$error && q('SELECT 1 FROM users WHERE lower(login_id) = lower(?)', [$f['id']])->fetch()) $error = 'This User ID is already taken.';

    $photo = null;
    if (!$error && !empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        // 서버리스(Vercel)는 디스크가 유지되지 않으므로 사진을 DB에 data URI 로 저장 (요청 크기 제한 때문에 3MB 까지)
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['photo']['tmp_name']);
        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/gif'], true) || $_FILES['photo']['size'] > 3 * 1024 * 1024) {
            $error = 'Photo must be PNG, JPG or GIF up to 3MB.';
        } else {
            $photo = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($_FILES['photo']['tmp_name']));
        }
    }

    if ($error) {
        $message = ['error', $error];
        $keep = $f;
    } else {
        q('INSERT INTO users (login_id, password_hash, ename, gname, birth, gender, phone, course, user_type, campus, photo, created_at)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?)', [
            $f['id'], password_hash($f['password'], PASSWORD_DEFAULT), strtoupper($f['ename']), $f['gname'], $f['birth'],
            $f['gender'], $f['phone'], $f['course'], $f['user_type'], $f['campus'], $photo, now()]);
        log_activity($me['login_id'], 'Registered', "Created {$f['user_type']} account {$f['id']} ({$f['campus']})");
        $message = ['success', ucfirst($f['user_type']) . " account \"{$f['id']}\" has been created. They can now sign in with this User ID and password."];
    }
}

$v = fn($k) => h($keep[$k] ?? '');
$sel = fn($k, $val) => (($keep[$k] ?? '') === $val) ? ' selected' : '';

admin_head('accounts', 'New Account');
topbar('New Account', 'Create a new student or admin ID');
?>
      <div class="account-form">
        <h2><i class="fa-solid fa-user-plus"></i> Create Account</h2>
<?php if ($message): ?>
        <div class="message <?= $message[0] ?>"><?= h($message[1]) ?></div>
<?php endif; ?>
        <form method="post" enctype="multipart/form-data" autocomplete="off">
          <h3>Basic Information</h3>
          <div class="form-grid">
            <div class="form-group"><label for="id">User ID *</label><input type="text" id="id" name="id" required value="<?= $v('id') ?>"></div>
            <div class="form-group"><label for="password">Password *</label><input type="text" id="password" name="password" required autocomplete="new-password"></div>
            <div class="form-group"><label for="ename">English Name *</label><input type="text" id="ename" name="ename" required value="<?= $v('ename') ?>"></div>
            <div class="form-group"><label for="gname">Global Name *</label><input type="text" id="gname" name="gname" required value="<?= $v('gname') ?>"></div>
            <div class="form-group"><label for="birth">Birth Date *</label><input type="date" id="birth" name="birth" required value="<?= $v('birth') ?>"></div>
            <div class="form-group"><label for="gender">Gender *</label>
              <select id="gender" name="gender" required>
                <option value="">Select Gender</option>
                <option value="Male"<?= $sel('gender', 'Male') ?>>Male</option>
                <option value="Female"<?= $sel('gender', 'Female') ?>>Female</option>
              </select>
            </div>
          </div>

          <h3>Contact &amp; Course</h3>
          <div class="form-grid">
            <div class="form-group"><label for="phone">Phone Number *</label><input type="tel" id="phone" name="phone" required value="<?= $v('phone') ?>"></div>
            <div class="form-group"><label for="course">Course *</label>
              <select id="course" name="course" required>
                <option value="">Select Course</option>
<?php foreach (COURSES as $c): ?>
                <option value="<?= h($c) ?>"<?= $sel('course', $c) ?>><?= h($c) ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="form-group"><label for="user_type">User Type *</label>
              <select id="user_type" name="user_type" required>
                <option value="student"<?= ($keep['user_type'] ?? 'student') === 'student' ? ' selected' : '' ?>>Student</option>
                <option value="admin"<?= $sel('user_type', 'admin') ?>>Admin</option>
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
            <div class="form-group"><label for="photo">Profile Photo (optional)</label><input type="file" id="photo" name="photo" accept="image/png,image/jpeg,image/gif"></div>
          </div>

          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Create Account</button>
        </form>
      </div>
<?php admin_foot();
