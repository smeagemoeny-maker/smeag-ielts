<?php
require_once __DIR__ . '/../includes/layout.php';
$me = require_admin();

$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register') {
    $test = strtoupper(str_replace(' ', '', trim($_POST['test'] ?? '')));
    $course = $_POST['course'] ?? '';
    $answers = [];
    for ($i = 1; $i <= 40; $i++) $answers[] = trim((string)($_POST["c$i"] ?? ''));

    if ($test === '' || !in_array($course, ['Listening', 'Reading', 'Writing'], true)) {
        $message = ['error', 'Please enter the test name and select a course.'];
    } else {
        q('INSERT INTO answer_keys (test_name, course, answers, created_at) VALUES (?,?,?,?)',
            [$test, $course, json_encode($answers, JSON_UNESCAPED_UNICODE), now()]);
        log_activity($me['login_id'], 'Updated', "Registered answers for $test $course");
        $message = ['success', "Answers for $test ($course) have been registered."];
    }
}

$searchTest = trim($_GET['search_test'] ?? '');
$searchCourse = $_GET['search_course'] ?? '';
$where = '1=1';
$params = [];
if ($searchTest !== '') { $where .= ' AND test_name LIKE ?'; $params[] = '%' . str_replace(' ', '', $searchTest) . '%'; }
if ($searchCourse !== '') { $where .= ' AND course = ?'; $params[] = $searchCourse; }

$total = (int)q("SELECT COUNT(*) FROM answer_keys WHERE $where", $params)->fetchColumn();
$page = max(1, min((int)($_GET['page'] ?? 1), max(1, $total)));
$item = q("SELECT * FROM answer_keys WHERE $where ORDER BY id DESC LIMIT 1 OFFSET " . ($page - 1), $params)->fetch();

admin_head('answer');
topbar('Answer Management', '답안 등록 및 관리');
?>
      <div class="add-answer-form">
        <h2><i class="fa-solid fa-plus"></i> Register Answer</h2>
<?php if ($message): ?>
        <div class="message <?= $message[0] ?>"><?= h($message[1]) ?></div>
<?php endif; ?>
        <form method="post">
          <input type="hidden" name="action" value="register">
          <div class="form-row">
            <div class="form-group">
              <label for="test">Test Name</label>
              <input type="text" id="test" name="test" required>
            </div>
            <div class="form-group">
              <label for="course">Course</label>
              <select id="course" name="course" required>
                <option value="">Select Course</option>
                <option value="Listening">Listening</option>
                <option value="Reading">Reading</option>
                <option value="Writing">Writing</option>
              </select>
            </div>
          </div>
          <div class="answer-inputs">
<?php for ($g = 0; $g < 4; $g++): ?>
            <div class="answer-input-group">
              <div class="answer-input-group-label">Answers <?= $g * 10 + 1 ?>-<?= $g * 10 + 10 ?></div>
<?php for ($i = $g * 10 + 1; $i <= $g * 10 + 10; $i++): ?>
              <div class="answer-input">
                <label for="c<?= $i ?>">Q<?= $i ?></label>
                <input type="text" id="c<?= $i ?>" name="c<?= $i ?>" placeholder="Answer">
              </div>
<?php endfor; ?>
            </div>
<?php endfor; ?>
          </div>
          <div style="margin-top:16px"><button type="submit" class="btn btn-primary">Register</button></div>
        </form>
      </div>

      <div class="search-section">
        <h2><i class="fa-solid fa-magnifying-glass"></i> Search Answer</h2>
        <form method="get" class="search-form">
          <div class="form-group">
            <label for="search_test">Search Test Name</label>
            <input type="text" id="search_test" name="search_test" placeholder="Enter test name" value="<?= h($searchTest) ?>">
          </div>
          <div class="form-group">
            <label for="search_course">Search Course</label>
            <select id="search_course" name="search_course">
              <option value="">All Courses</option>
<?php foreach (['Listening', 'Reading', 'Writing'] as $c): ?>
              <option value="<?= $c ?>"<?= $c === $searchCourse ? ' selected' : '' ?>><?= $c ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <button type="submit" class="btn btn-primary">Search</button>
            <a href="answer.php" class="btn btn-secondary">Reset</a>
          </div>
        </form>
      </div>

      <div class="answers-section">
        <h2><i class="fa-solid fa-list"></i> Answer List (Total: <?= $total ?>)</h2>
<?php if (!$item): ?>
        <p style="text-align:center;color:#888;padding:40px 0;">No results found.</p>
<?php else: $ans = json_decode($item['answers'], true) ?: []; ?>
        <div class="answers-grid">
          <div class="answer-card">
            <div class="answer-header">
              <div class="answer-info">
                <span class="answer-number">#<?= (int)$item['id'] ?></span>
                <span class="answer-test"><?= h($item['test_name']) ?></span>
                <span class="answer-course"><?= h($item['course']) ?></span>
              </div>
              <div class="answer-date"><?= h(substr($item['created_at'], 0, 16)) ?></div>
            </div>
            <div class="answer-content">
<?php for ($g = 0; $g < 4; $g++): ?>
              <div class="answer-section">
                <h4>Answers <?= $g * 10 + 1 ?>-<?= $g * 10 + 10 ?></h4>
                <div class="answer-list">
<?php for ($i = $g * 10; $i < $g * 10 + 10; $i++): ?>
                  <div class="answer-item"><span class="question-num">Q<?= $i + 1 ?></span><span class="answer-text"><?= h($ans[$i] ?? '') ?></span></div>
<?php endfor; ?>
                </div>
              </div>
<?php endfor; ?>
            </div>
          </div>
        </div>
<?php
        // 페이지네이션: 현재 페이지 앞뒤 2개 (원본과 같음)
        $last = $total;
        echo '<div class="pagination">';
        if ($page > 1) {
            echo '<a href="' . h(qs(['page' => 1])) . '">First</a>';
            echo '<a href="' . h(qs(['page' => $page - 1])) . '">Prev</a>';
        }
        for ($i = max(1, $page - 2); $i <= min($last, $page + 2); $i++) {
            echo '<a href="' . h(qs(['page' => $i])) . '"' . ($i === $page ? ' class="active"' : '') . ">$i</a>";
        }
        if ($page < $last) {
            echo '<a href="' . h(qs(['page' => $page + 1])) . '">Next</a>';
            echo '<a href="' . h(qs(['page' => $last])) . '">Last</a>';
        }
        echo '</div>';
?>
<?php endif; ?>
      </div>
<?php admin_foot();
