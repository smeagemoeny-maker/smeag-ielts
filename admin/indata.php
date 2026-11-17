<?php
require_once __DIR__ . '/../includes/layout.php';
$me = require_admin();

$type = in_array($_GET['type'] ?? '', INDATA_TYPES, true) ? $_GET['type'] : 'IELTS ACADEMIC';

// ---- 점수 저장 (모달 POST) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modal_save'])) {
    $uid = (int)($_POST['id'] ?? 0);
    $postType = in_array($_POST['type'] ?? '', INDATA_TYPES, true) ? $_POST['type'] : $type;
    $student = q("SELECT * FROM users WHERE id = ? AND user_type = 'student'", [$uid])->fetch();
    $testDate = $_POST['test_date'] ?? '';
    if ($student && preg_match('/^\d{4}-\d{2}-\d{2}$/', $testDate)) {
        q('INSERT INTO score_entries (user_id, test_type, test_date, listening_score, reading_score, speaking_score, writing_score, sa_teacher_comment, updated_by, created_at)
           VALUES (?,?,?,?,?,?,?,?,?,?)', [
            $uid, $postType, $testDate,
            trim($_POST['listening_score'] ?? '') ?: null, trim($_POST['reading_score'] ?? '') ?: null,
            trim($_POST['speaking_score'] ?? '') ?: null, trim($_POST['writing_score'] ?? '') ?: null,
            trim($_POST['sa_teacher_comment'] ?? ''), $me['id'], now()]);
        log_activity($me['login_id'], 'Updated', "Entered $postType score for {$student['ename']}");
        $_SESSION['flash'] = ['success', "Score saved for {$student['ename']} ($postType, $testDate)."];
    } else {
        $_SESSION['flash'] = ['error', 'Could not save the score. Please check the input.'];
    }
    header('Location: indata.php' . qs([]));
    exit;
}
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ---- 목록 ----
$ename = trim($_GET['ename'] ?? '');
$course = trim($_GET['course'] ?? '');
$date = trim($_GET['date'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

$names = q("SELECT DISTINCT ename FROM users WHERE user_type = 'student' ORDER BY ename")->fetchAll(PDO::FETCH_COLUMN);
$where = "user_type = 'student'";
$params = [];
if ($ename !== '') { $where .= ' AND ename = ?'; $params[] = $ename; }
if ($course !== '') {
    // 등록 과정이 달라도 그 시험(IELTS ACADEMIC / GENERAL)을 본 학생은 보이게
    $where .= ' AND (course = ? OR id IN (SELECT user_id FROM test_attempts WHERE test_type = ?))';
    array_push($params, $course, $course);
}
if ($date !== '') {
    $where .= ' AND (id IN (SELECT user_id FROM test_attempts WHERE substr(submitted_at,1,10) = ?)
                  OR id IN (SELECT user_id FROM score_entries WHERE test_date = ?))';
    array_push($params, $date, $date);
}
$total = (int)q("SELECT COUNT(*) FROM users WHERE $where", $params)->fetchColumn();
$totalPages = (int)ceil($total / $perPage);
$page = min($page, max(1, $totalPages));
$rows = q("SELECT id, ename, course FROM users WHERE $where ORDER BY ename LIMIT $perPage OFFSET " . (($page - 1) * $perPage), $params)->fetchAll();

// 탭에 따라 모달 입력칸이 달라짐
$fields = match ($type) {
    'IELTS ACADEMIC', 'IELTS GENERAL' => ['listening', 'reading', 'writing'],
    'TOEIC' => ['listening', 'reading'],
    default => ['listening', 'reading', 'speaking', 'writing'],
};

admin_head('indata');
topbar('Enter Score and Comment', 'You can enter and manage test scores and comments.', false);
?>
      <div class="section">
<?php if ($flash): ?>
        <div class="flash <?= $flash[0] ?>"><?= h($flash[1]) ?></div>
<?php endif; ?>
        <div class="test-tabs">
<?php foreach (INDATA_TYPES as $t): ?>
          <form method="get" style="display:inline">
            <input type="hidden" name="type" value="<?= h($t) ?>">
            <button type="submit" class="test-tab<?= $t === $type ? ' active' : '' ?>"><?= h($t) ?></button>
          </form>
<?php endforeach; ?>
        </div>

        <form method="get" class="search-bar">
          <input type="hidden" name="type" value="<?= h($type) ?>">
          <select name="ename" style="min-width:360px">
            <option value="">Select Name</option>
<?php foreach ($names as $n): ?>
            <option value="<?= h($n) ?>"<?= $n === $ename ? ' selected' : '' ?>><?= h($n) ?></option>
<?php endforeach; ?>
          </select>
          <select name="course" style="min-width:180px">
            <option value="">Select Course</option>
<?php foreach (['ESL', 'IELTS ACADEMIC', 'IELTS GENERAL', 'SPEAKING MASTER', 'TOEFL', 'TOEIC'] as $c): ?>
            <option value="<?= h($c) ?>"<?= $c === $course ? ' selected' : '' ?>><?= h($c) ?></option>
<?php endforeach; ?>
          </select>
          <input type="date" name="date" value="<?= h($date) ?>">
          <button type="submit">Search</button>
        </form>

        <table class="scores-table">
          <tr><th>Name</th><th>Course</th><th>Enter Score/Comment</th><th>Info</th><th>PDF</th><th>Check Answer</th></tr>
<?php if (!$rows): ?>
          <tr><td colspan="6" class="empty-row">No data found.</td></tr>
<?php endif; ?>
<?php foreach ($rows as $r): $js = h(json_encode(['id' => (int)$r['id'], 'ename' => $r['ename'], 'course' => $r['course']], JSON_UNESCAPED_UNICODE)); ?>
          <tr>
            <td><?= h($r['ename']) ?></td>
            <td><?= h($r['course']) ?></td>
            <td><button type="button" class="score-btn" onclick="openScoreModal(<?= $js ?>)">Enter Score/Comment</button></td>
            <td><button type="button" class="score-btn" onclick="openInfoPanel(<?= $js ?>)">INFO</button></td>
            <td><button type="button" class="score-btn" onclick="showPdfPanel(<?= $js ?>)">PDF</button></td>
            <td><button type="button" class="score-btn" onclick="openCheckAnswerPanel(<?= $js ?>)">Check Answer</button></td>
          </tr>
<?php endforeach; ?>
        </table>
<?php button_pagination($page, $totalPages); ?>

        <!-- PDF Download 패널 -->
        <div id="pdfDownloadPanel" class="pdf-panel" style="display:none">
          <h3>PDF Download</h3>
          <input type="hidden" id="pdfUserId">
          <label for="pdfCourse">Select Course</label>
          <select id="pdfCourse" onchange="loadPdfDates()">
<?php foreach (COURSES as $c): ?>
            <option value="<?= h($c) ?>"><?= h($c) ?></option>
<?php endforeach; ?>
          </select>
          <label for="pdfDate">Select Date</label>
          <select id="pdfDate"></select>
          <div id="pdfNoDates" class="pdf-nodates" style="display:none">No test dates found.</div>
          <button type="button" class="pdf-download-btn" onclick="downloadPdf()">Download PDF</button>
        </div>

        <!-- Check Answer 패널 -->
        <div id="answerCheckPanel" class="check-panel" style="display:none">
          <div class="check-panel-head">
            <h3 id="checkTitle">IELTS Test History</h3>
            <span class="panel-close" onclick="closeCheckPanel()">&times;</span>
          </div>
          <div class="history-bulk">
            <button type="button" id="selectAllBtn" class="mini-btn check" onclick="toggleAllHistory(!document.getElementById('selectAllHistory').checked)" disabled>Select all</button>
            <button type="button" id="deleteSelectedBtn" class="mini-btn del" onclick="deleteSelected()" disabled>Delete selected</button>
            <span id="selectedCount"></span>
          </div>
          <table class="history-table">
            <thead><tr><th style="width:34px;text-align:center"><input type="checkbox" id="selectAllHistory" title="Select all" onchange="toggleAllHistory(this.checked)"></th><th>Date</th><th>Test Set</th><th>Course</th><th>Subjects</th><th title="Per skill: times the student switched to another program / times the student exited full screen (Esc)">Left Screen / Exited Full Screen</th><th style="text-align:center">Action</th></tr></thead>
            <tbody id="historyBody"></tbody>
          </table>
          <div id="detailResults"></div>
        </div>
      </div>

      <!-- 점수 입력 모달 -->
      <div id="scoreModal">
        <div class="modal-box">
          <span class="panel-close" onclick="closeScoreModal()">&times;</span>
          <h2>Enter Score and Comment</h2>
          <form method="post">
            <input type="hidden" name="modal_save" value="1">
            <input type="hidden" name="id" id="m_id">
            <input type="hidden" name="ename" id="m_ename">
            <input type="hidden" name="course" id="m_course">
            <input type="hidden" name="type" value="<?= h($type) ?>">
            <label for="m_date">Test Date</label>
            <input type="date" name="test_date" id="m_date" required>
<?php foreach ($fields as $f): $label = ucfirst($f) . ' Score'; ?>
            <label for="m_<?= $f ?>"><?= $label ?></label>
            <input type="number" step="any" name="<?= $f ?>_score" id="m_<?= $f ?>" placeholder="<?= $label ?>">
<?php endforeach; ?>
            <label for="m_comment">SA TEACHER COMMENT</label>
            <textarea name="sa_teacher_comment" id="m_comment" rows="4" placeholder="SA TEACHER COMMENT"></textarea>
            <button type="submit" class="save-btn">Save</button>
          </form>
        </div>
      </div>

      <!-- INFO 모달 -->
      <div id="infoModal">
        <div class="modal-box">
          <span class="panel-close" onclick="document.getElementById('infoModal').classList.remove('show')">&times;</span>
          <h2>Student Info</h2>
          <div id="infoBody">Loading...</div>
        </div>
      </div>
<?php admin_foot('<script src="../js/indata.js"></script>');
