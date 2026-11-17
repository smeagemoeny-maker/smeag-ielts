<?php
// 응시 중인 시험 (exam_drafts) — 정전·브라우저 꺼짐·다른 PC 로 다시 로그인해도 멈춘 곳에서 이어서 보기
//   answers     : 지금까지 쓴 답안 (JSON)
//   elapsed     : 실제로 시험 화면이 켜져 있던 시간(초). 정전으로 꺼져 있던 동안은 세지 않음 → 남은 시간 = 제한 시간 - elapsed
//   audio_pos   : Listening 음원 재생 위치(초) → 다시 열면 그 자리부터 재생
//   single_mode : Individual Skill Test (한 영역만) 여부 — 다시 로그인하면 세션이 바뀌므로 여기에 남김
// 제출하면 지움. 오래된 테이블이면 처음 쓸 때 칸을 더함 (db/schema*.sql 에도 있음)
require_once __DIR__ . '/bootstrap.php';

const DRAFT_IDLE_MAX = 12 * 3600;   // 12시간 동안 아무 진행이 없으면 버림 (다음 날 같은 세트를 다시 볼 때 옛 답안이 뜨지 않게)

function draft_q(string $sql, array $params = []): PDOStatement
{
    try {
        return q($sql, $params);
    } catch (PDOException $e) {
        draft_migrate();
        return q($sql, $params);
    }
}

function draft_migrate(): void
{
    db()->exec('CREATE TABLE IF NOT EXISTS exam_drafts (
        user_id INTEGER NOT NULL, test_set VARCHAR(30) NOT NULL, section VARCHAR(20) NOT NULL,
        answers TEXT NOT NULL, started_at INTEGER NOT NULL, updated_at INTEGER NOT NULL,
        elapsed INTEGER NOT NULL DEFAULT 0, audio_pos REAL NOT NULL DEFAULT 0, single_mode INTEGER NOT NULL DEFAULT 0,
        PRIMARY KEY (user_id, test_set, section))');
    foreach (['elapsed INTEGER NOT NULL DEFAULT 0', 'audio_pos REAL NOT NULL DEFAULT 0', 'single_mode INTEGER NOT NULL DEFAULT 0',
              'leave_count INTEGER NOT NULL DEFAULT 0'] as $col) {
        try { db()->exec("ALTER TABLE exam_drafts ADD COLUMN $col"); } catch (PDOException) { /* 이미 있음 */ }
    }
    if (db_driver() === 'pgsql') db()->exec('ALTER TABLE exam_drafts ENABLE ROW LEVEL SECURITY');
    attempts_migrate();
}

/** test_attempts.leave_count — 시험 중 시험 화면을 떠난 횟수 (다른 프로그램으로 전환). NULL = 기록 전 응시 */
function attempts_migrate(): void
{
    try { db()->exec('ALTER TABLE test_attempts ADD COLUMN leave_count INTEGER'); } catch (PDOException) { /* 이미 있음 */ }
}

/** 칸이 없는 옛 DB 면 칸을 더하고 다시 실행 */
function attempt_q(string $sql, array $params = []): PDOStatement
{
    try {
        return q($sql, $params);
    } catch (PDOException $e) {
        attempts_migrate();
        return q($sql, $params);
    }
}

/** 마지막으로 진행이 있던 시각 */
function draft_last_seen(array $row): int
{
    return max((int)$row['started_at'], (int)$row['updated_at']);
}

/**
 * 브라우저가 보낸 사용 시간·음원 위치를 받되, 줄일 수 없고 실제로 흐른 시간보다 빨리 늘릴 수도 없게 함
 * → [elapsed, audio_pos]
 */
function draft_progress(array $row, $elapsed, $pos): array
{
    $gap = max(0, time() - draft_last_seen($row)) + 5;
    $oldE = (int)$row['elapsed'];
    $oldP = (float)$row['audio_pos'];
    $e = is_numeric($elapsed) ? (int)$elapsed : $oldE;
    $p = is_numeric($pos) ? (float)$pos : $oldP;
    return [min(max($e, $oldE), $oldE + $gap), min(max($p, $oldP), $oldP + $gap)];
}

/** 학생이 응시 중인(제출 전) 시험들 — 학생 첫 화면의 "Resume Test" */
function active_drafts(int $userId): array
{
    try {
        $rows = draft_q('SELECT test_set, section, started_at, updated_at, single_mode FROM exam_drafts WHERE user_id = ?', [$userId])->fetchAll();
    } catch (PDOException) {
        return [];
    }
    $out = [];
    foreach ($rows as $r) {
        if (time() - draft_last_seen($r) > DRAFT_IDLE_MAX) continue;
        if (!preg_match('/^(ACADEMIC|GENERAL)(\d+)$/', (string)$r['test_set'], $m)) continue;
        $sec = (string)$r['section'];
        if (!in_array($sec, ['listening', 'reading', 'writing'], true)) continue;
        $kind = strtolower($m[1]);
        $out[] = [
            'label' => $m[1] . ' ' . (int)$m[2] . ' · ' . ucfirst($sec),
            'url' => "Courses/Ielts/$kind/{$r['test_set']}/$sec/{$sec}corse.php",
            'seen' => draft_last_seen($r),
        ];
    }
    usort($out, fn($a, $b) => $b['seen'] <=> $a['seen']);
    return $out;
}
