<?php
// Individual Skill Test — IELTS Academic / General 에서 한 영역(Listening · Reading · Writing)만 골라 응시
//   세트를 누르면 그 영역 안내 페이지를 ?single=1 로 엶 → 제출하면 다음 영역으로 넘어가지 않고 끝 화면 (Courses/Ielts/_engine/context.php)
require_once __DIR__ . '/../includes/student_layout.php';
$me = require_student();

const SKILL_MODULES = ['academic' => ['ACADEMIC', 'IELTS ACADEMIC'], 'general' => ['GENERAL', 'IELTS GENERAL']];
const SKILLS = [
    'listening' => ['fa-solid fa-headphones', 'listninginfo.php', 'About 30 minutes (length of the audio)'],
    'reading'   => ['fa-solid fa-book-open-reader', 'readinginfo.php', '60 minutes · 3 passages · 40 questions'],
    'writing'   => ['fa-solid fa-pen', 'writinginfo.php', '60 minutes · Task 1 + Task 2'],
];

$kind = isset(SKILL_MODULES[$_GET['kind'] ?? '']) ? $_GET['kind'] : '';
$skill = isset(SKILLS[$_GET['skill'] ?? '']) ? $_GET['skill'] : '';

// 이 학생이 고른 모듈·영역에서 이미 본 세트 (최근 점수 표시)
$taken = [];
if ($kind && $skill) {
    [$prefix, $testType] = SKILL_MODULES[$kind];
    foreach (q('SELECT test_set, score, submitted_at FROM test_attempts WHERE user_id = ? AND test_type = ? AND section = ? ORDER BY submitted_at',
            [$me['id'], $testType, strtoupper($skill)])->fetchAll() as $r) {
        $taken[$r['test_set']] = $r;   // 같은 세트를 여러 번 봤으면 마지막 것
    }
}

student_head('skill', 'Individual Skill Test - SMEAG');
?>
<style>
.sk-step { text-align: center; color: #6c63ff; margin: 6px 0 14px; font-size: 1.05rem; }
.sk-step b { display: inline-block; width: 26px; height: 26px; line-height: 26px; border-radius: 50%; background: #6c63ff; color: #fff; margin-right: 6px; font-size: .9rem; }
.sk-row { display: flex; gap: 22px; justify-content: center; flex-wrap: wrap; margin-bottom: 30px; }
.sk-row .etest-card { text-decoration: none; min-width: 230px; }
.sk-row .etest-card small { display: block; margin-top: 8px; font-size: .82rem; font-weight: 500; color: #888; letter-spacing: 0; }
.sk-row .etest-card.selected small { color: #f3f0ff; }
.sk-set { position: relative; }
.sk-set .sk-badge { display: block; font-size: .75rem; font-weight: 600; opacity: .85; margin-top: 2px; }
</style>
      <div class="etest-title">Individual Skill Test</div>
      <div class="etest-desc">Practise one IELTS skill at a time. Choose a module and a skill, then pick a test set. Only that section is taken, and your result is shown when you submit.</div>

      <div class="sk-step"><b>1</b>Choose a module</div>
      <div class="sk-row">
<?php foreach (SKILL_MODULES as $k => [$prefix, $label]): ?>
        <a class="etest-card<?= $k === $kind ? ' selected' : '' ?>" href="?<?= h(http_build_query(array_filter(['kind' => $k, 'skill' => $skill]))) ?>">
          <i class="fa-solid fa-book-open"></i><?= h($label) ?> TEST
        </a>
<?php endforeach; ?>
      </div>

<?php if ($kind): ?>
      <div class="sk-step"><b>2</b>Choose a skill</div>
      <div class="sk-row">
<?php foreach (SKILLS as $s => [$icon, , $info]): ?>
        <a class="etest-card<?= $s === $skill ? ' selected' : '' ?>" href="?<?= h(http_build_query(['kind' => $kind, 'skill' => $s])) ?>">
          <i class="<?= $icon ?>"></i><?= strtoupper($s) ?>
          <small><?= h($info) ?></small>
        </a>
<?php endforeach; ?>
      </div>
<?php endif; ?>

<?php if ($kind && $skill): ?>
<?php [$prefix] = SKILL_MODULES[$kind]; $infoFile = SKILLS[$skill][1]; ?>
      <div class="sk-step"><b>3</b>Choose a test set</div>
      <div class="etest-<?= $kind ?>-wrap" style="display:block">
        <h3 style="text-align:center;color:#6c63ff;margin:0 0 10px;"><?= $prefix ?> · <?= ucfirst($skill) ?> Test Sets</h3>
        <div class="etest-<?= $kind ?>-list">
<?php for ($n = 1; $n <= SET_COUNT; $n++): $t = $taken["$prefix $n"] ?? null; ?>
          <button type="button" class="<?= $kind ?>-btn sk-set"
            title="<?= $t ? 'Last taken ' . h(substr((string)$t['submitted_at'], 0, 10)) : 'Not taken yet' ?>"
            onclick="location.href='../Courses/Ielts/<?= $kind ?>/<?= $prefix . $n ?>/<?= $skill ?>/<?= $infoFile ?>?single=1'"><?= $prefix ?> <?= $n ?>
<?php if ($t): ?>
            <span class="sk-badge"><?= $skill === 'writing' ? '✓ Submitted' : '✓ ' . (int)$t['score'] . ' / 40' ?></span>
<?php endif; ?>
          </button>
<?php endfor; ?>
        </div>
      </div>
<?php endif; ?>
<?php
student_foot();
