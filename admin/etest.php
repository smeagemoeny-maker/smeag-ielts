<?php
require_once __DIR__ . '/../includes/layout.php';
require_admin();

$cards = [
    ['ESL TEST', 'fa-solid fa-book-open', ''],
    ['TOEIC TEST', 'fa-solid fa-book-open', ''],
    ['TOEFL TEST', 'fa-solid fa-book-open', ''],
    ['IELTS ACADEMIC TEST', 'fa-solid fa-book-open', 'academic'],
    ['IELTS GENERAL TEST', 'fa-solid fa-book-open', 'general'],
    ['BUSINESS TEST', 'fa-solid fa-book-open', ''],
    ['SPEAKING MASTER TEST', 'fa-solid fa-microphone', ''],
];

admin_head('etest');
?>
      <div class="etest-title">English TEST Courses</div>
      <div class="etest-desc">Select a test course to manage or view details. Click on IELTS ACADEMIC TEST or IELTS GENERAL TEST to see available test sets.</div>

      <div class="etest-courses">
<?php foreach ($cards as [$name, $icon, $kind]): ?>
        <div class="etest-card" data-kind="<?= $kind ?>" onclick="selectCourse(this)">
          <i class="<?= $icon ?>"></i>
          <?= h($name) ?>
        </div>
<?php endforeach; ?>
      </div>

<?php foreach (['academic' => 'ACADEMIC', 'general' => 'GENERAL'] as $kind => $prefix): ?>
      <div class="etest-<?= $kind ?>-wrap" id="wrap-<?= $kind ?>" style="display:none">
        <h3 style="text-align:center;color:#6c63ff;margin:0 0 10px;">Available <?= $prefix ?> Test Sets</h3>
        <div class="etest-<?= $kind ?>-list">
<?php for ($n = 1; $n <= SET_COUNT; $n++): ?>
          <button type="button" class="<?= $kind ?>-btn"
            onclick="location.href='../Courses/Ielts/<?= $kind ?>/<?= $prefix . $n ?>/listening/listninginfo.php'"><?= $prefix ?> <?= $n ?></button>
<?php endfor; ?>
        </div>
      </div>
<?php endforeach; ?>

<?php admin_foot(<<<'JS'
<script>
function selectCourse(card) {
  document.querySelectorAll('.etest-card').forEach(c => c.classList.remove('selected'));
  card.classList.add('selected');
  const kind = card.dataset.kind;
  document.getElementById('wrap-academic').style.display = kind === 'academic' ? 'block' : 'none';
  document.getElementById('wrap-general').style.display = kind === 'general' ? 'block' : 'none';
}
</script>
JS);
