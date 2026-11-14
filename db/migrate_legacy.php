<?php
// 레거시(원본 MySQL smeag_etest) → 앱 테이블(public.users, answer_keys, test_attempts, score_entries, schedules) 변환.
// 원본 사본은 같은 Postgres 의 ielts_legacy 스키마에 미리 들어 있어야 함 (원본 테이블·컬럼 그대로).
//
// 사용: SMEAG_DATABASE_URL='postgresql://postgres:<pw>@db.<ref>.supabase.co:5432/postgres' php db/migrate_legacy.php [--replace]
//   --replace : 앱 테이블에 이미 데이터가 있으면 비우고 다시 채움 (없으면 중단)
// 전체가 한 트랜잭션 — 검증에 실패하면 아무것도 바뀌지 않음.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }   // 웹에서 실행 금지 (db/ 는 라우터에서도 막혀 있음)
ini_set('memory_limit', '1G');

// 채점 함수는 앱 코드(includes/bootstrap.php)에서 그대로 가져옴 → 앱 화면과 점수가 같음
$src = file_get_contents(__DIR__ . '/../includes/bootstrap.php');
if (!preg_match('#function normalize_answer.*?\n}\n.*?function answer_alternatives.*?\n}\n.*?function is_correct.*?\n}\n.*?function key_name.*?\n}\n#s', $src, $m)) {
    fwrite(STDERR, "bootstrap.php 에서 채점 함수를 찾지 못함\n");
    exit(1);
}
eval($m[0]);
if (!preg_match('#function grade\(.*?\n}\n#s', $src, $g)) { fwrite(STDERR, "grade() 없음\n"); exit(1); }
eval($g[0]);

$url = getenv('SMEAG_DATABASE_URL') ?: getenv('DATABASE_URL');
if (!$url) { fwrite(STDERR, "SMEAG_DATABASE_URL 필요\n"); exit(1); }
$p = parse_url($url);
$pdo = new PDO(sprintf('pgsql:host=%s;port=%d;dbname=%s;sslmode=require', $p['host'], $p['port'] ?? 5432, ltrim($p['path'] ?? '/postgres', '/')),
    urldecode($p['user']), urldecode($p['pass']), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$replace = in_array('--replace', $argv, true);
$all = fn(string $sql, array $a = []) => (function () use ($pdo, $sql, $a) { $st = $pdo->prepare($sql); $st->execute($a); return $st->fetchAll(); })();
$one = fn(string $sql, array $a = []) => (function () use ($pdo, $sql, $a) { $st = $pdo->prepare($sql); $st->execute($a); return $st->fetchColumn(); })();

function bulk(PDO $pdo, string $table, array $cols, array $rows, int $chunk = 500): void
{
    foreach (array_chunk($rows, $chunk) as $part) {
        $ph = '(' . implode(',', array_fill(0, count($cols), '?')) . ')';
        $pdo->prepare("INSERT INTO $table (" . implode(',', $cols) . ') VALUES ' . implode(',', array_fill(0, count($part), $ph)))
            ->execute(array_merge(...array_map('array_values', $part)));
    }
}

if ((int)$one("SELECT count(*) FROM information_schema.tables WHERE table_schema='ielts_legacy' AND table_name='smeag_userdb'") === 0) {
    fwrite(STDERR, "ielts_legacy 스키마가 없음 — 원본 사본을 먼저 적재하세요\n");
    exit(1);
}

$pdo->beginTransaction();
$pdo->exec(file_get_contents(__DIR__ . '/schema_pgsql.sql'));
$existing = (int)$one('SELECT count(*) FROM users');
if ($existing > 0) {
    if (!$replace) { $pdo->rollBack(); fwrite(STDERR, "public.users 에 이미 $existing 명 — 다시 채우려면 --replace\n"); exit(1); }
    $pdo->exec('TRUNCATE users, answer_keys, test_attempts, score_entries, schedules, activity_log, sessions RESTART IDENTITY CASCADE');
}
$ts = "to_char(%s, 'YYYY-MM-DD HH24:MI:SS')";

// 1) 사용자 — 비밀번호는 원본 bcrypt($2y$) 그대로 → PHP password_verify 로 기존 비밀번호 그대로 로그인
$pdo->exec("INSERT INTO users (login_id, password_hash, ename, gname, birth, gender, phone, course, user_type, campus, photo, created_at)
    SELECT id, password, coalesce(ename, id), gname, birth, gender, phone, course, user_type,
           CASE WHEN campus LIKE 'BAYDREAM%' THEN 'BAYDREAM' ELSE campus END, nullif(photo, ''), " . sprintf($ts, 'date') . "
    FROM ielts_legacy.smeag_userdb ORDER BY num");
// 응시 기록만 있고 회원 정보가 없는 ID → 로그인 불가 자리표시 계정 (기록 보존용)
$orphans = $pdo->exec("INSERT INTO users (login_id, password_hash, ename, user_type, created_at)
    SELECT id, '!legacy-no-account', max(ename), 'student', " . sprintf($ts, 'min(date)') . " FROM (
      SELECT id, ename, date FROM ielts_legacy.ielts_ac_listening_answer UNION ALL SELECT id, ename, date FROM ielts_legacy.ielts_ac_reading_answer
      UNION ALL SELECT id, ename, date FROM ielts_legacy.ielts_ac_writing_answer UNION ALL SELECT id, ename, date FROM ielts_legacy.ielts_gn_listening_answer
      UNION ALL SELECT id, ename, date FROM ielts_legacy.ielts_gn_reading_answer UNION ALL SELECT id, ename, date FROM ielts_legacy.ielts_gn_writing_answer) a
    WHERE id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM users u WHERE u.login_id = a.id) GROUP BY id ORDER BY min(date)");
$uid = array_column($all('SELECT login_id, id FROM users'), 'id', 'login_id');

// 2) 정답 — test 'ACADEMIC18' / course 'Listening' 형식 그대로, c1~c40 → JSON 배열
$cs = implode(',', array_map(fn($i) => "coalesce(c$i,'')", range(1, 40)));
$pdo->exec("INSERT INTO answer_keys (test_name, course, answers, created_at)
    SELECT upper(replace(test,' ','')), course, json_build_array($cs)::text, " . sprintf($ts, 'date') . "
    FROM ielts_legacy.academic_correct ORDER BY date, num");
$keys = [];
foreach ($all('SELECT test_name, course, answers FROM answer_keys ORDER BY id') as $k) {
    $keys[strtoupper($k['test_name']) . '|' . strtoupper($k['course'])] = json_decode($k['answers'], true);   // 뒤 id 가 이김 = 앱 answer_key()
}

// 3) 응시 기록 — 앱 grade() 로 점수 계산 (앱 Check 화면과 같은 점수). 원본 행에 저장된 정답(correctN)으로 낸 점수는 비교용으로만 집계
$sources = [
    ['ielts_ac_listening_answer', 'IELTS ACADEMIC', 'LISTENING', 'lst'], ['ielts_ac_reading_answer', 'IELTS ACADEMIC', 'READING', 'rs'],
    ['ielts_gn_listening_answer', 'IELTS GENERAL', 'LISTENING', 'lst'], ['ielts_gn_reading_answer', 'IELTS GENERAL', 'READING', 'rs'],
];
$stats = ['rows' => 0, 'nokey' => 0, 'diff_vs_rowkey' => 0, 'sum_app' => 0, 'sum_rowkey' => 0];
$rows = [];
foreach ($sources as [$t, $type, $sec, $pre]) {
    $ans = implode(',', array_map(fn($i) => "$pre$i", range(1, 40)));
    $cor = implode(',', array_map(fn($i) => "correct$i", range(1, 40)));
    foreach ($all("SELECT _row, id, level, " . sprintf($ts, 'date') . " d, $ans, $cor FROM ielts_legacy.$t ORDER BY date, _row") as $r) {
        $given = []; $rowKey = [];
        for ($i = 1; $i <= 40; $i++) { $given[] = (string)($r["$pre$i"] ?? ''); $rowKey[] = (string)($r["correct$i"] ?? ''); }
        $set = (string)$r['level'];
        $key = $keys[key_name($set) . '|' . $sec] ?? null;
        $score = grade($given, $key);
        $rk = grade($given, $rowKey);
        $stats['rows']++; $stats['nokey'] += $key ? 0 : 1; $stats['diff_vs_rowkey'] += $score !== $rk ? 1 : 0;
        $stats['sum_app'] += $score; $stats['sum_rowkey'] += $rk;
        $rows[] = [$r['d'], $uid[$r['id']], $type, $set, $sec, json_encode($given, JSON_UNESCAPED_UNICODE), null, null, $score];
    }
}
foreach ([['ielts_ac_writing_answer', 'IELTS ACADEMIC'], ['ielts_gn_writing_answer', 'IELTS GENERAL']] as [$t, $type]) {
    foreach ($all("SELECT id, level, " . sprintf($ts, 'date') . " d, wt1, wt2 FROM ielts_legacy.$t ORDER BY date, _row") as $r) {
        $rows[] = [$r['d'], $uid[$r['id']], $type, (string)$r['level'], 'WRITING', null, $r['wt1'], $r['wt2'], 0];
    }
}
usort($rows, fn($a, $b) => $a[0] <=> $b[0]);   // id 가 시간 순이 되도록
bulk($pdo, 'test_attempts', ['submitted_at', 'user_id', 'test_type', 'test_set', 'section', 'answers', 'writing_task1', 'writing_task2', 'score'], $rows);

// 4) 교사 점수 — 원본은 학생을 이름(ename)으로만 저장 → 같은 이름의 학생(없으면 아무 역할) 중 먼저 가입한 사람
$unmatched = [];
foreach ([['ielts_academic_scores', 'IELTS ACADEMIC'], ['ielts_general_scores', 'IELTS GENERAL'], ['esl_scores', 'ESL'], ['business_scores', 'BUSINESS']] as [$t, $type]) {
    $hasSpk = (bool)$one("SELECT count(*) FROM information_schema.columns WHERE table_schema='ielts_legacy' AND table_name=? AND column_name='speaking_score'", [$t]);
    foreach ($all("SELECT ename, date::text d, listening_score::text l, reading_score::text r, " . ($hasSpk ? 'speaking_score::text' : 'NULL') . " s, writing_score::text w,
                   sa_teacher_comment c, " . sprintf($ts, 'created_at') . " ca FROM ielts_legacy.$t ORDER BY id") as $r) {
        $u = $one("SELECT id FROM users WHERE ename = ? ORDER BY (user_type = 'student') DESC, id LIMIT 1", [$r['ename']]);
        if (!$u) { $unmatched[] = "$t:{$r['ename']}"; continue; }
        bulk($pdo, 'score_entries', ['user_id', 'test_type', 'test_date', 'listening_score', 'reading_score', 'speaking_score', 'writing_score', 'sa_teacher_comment', 'created_at'],
            [[$u, $type, $r['d'], $r['l'], $r['r'], $r['s'], $r['w'], $r['c'], $r['ca']]]);
    }
}

// 5) 일정 — student_id 는 로그인 ID
$schedSkip = [];
foreach ($all("SELECT student_id, test_date::text d, to_char(test_time,'HH24:MI:SS') t, test_subject, test_type, location, notes, " . sprintf($ts, 'created_at') . " ca FROM ielts_legacy.student_schedules ORDER BY id") as $r) {
    if (!isset($uid[$r['student_id']])) { $schedSkip[] = $r['student_id']; continue; }
    bulk($pdo, 'schedules', ['student_id', 'test_date', 'test_time', 'test_subject', 'test_type', 'location', 'notes', 'created_at'],
        [[$uid[$r['student_id']], $r['d'], $r['t'], $r['test_subject'], $r['test_type'], $r['location'], $r['notes'], $r['ca']]]);
}
$pdo->prepare('INSERT INTO activity_log (user_label, action, details, created_at) VALUES (?,?,?,?)')
    ->execute(['system', 'Imported', 'Legacy data migrated from smeag_etest (MySQL)', date('Y-m-d H:i:s')]);

// 6) 검증 — 원본 건수와 같아야 커밋
$expect = [
    'users' => (int)$one('SELECT count(*) FROM ielts_legacy.smeag_userdb') + $orphans,
    'answer_keys' => (int)$one('SELECT count(*) FROM ielts_legacy.academic_correct'),
    'test_attempts' => (int)$one('SELECT (SELECT count(*) FROM ielts_legacy.ielts_ac_listening_answer)+(SELECT count(*) FROM ielts_legacy.ielts_ac_reading_answer)
        +(SELECT count(*) FROM ielts_legacy.ielts_ac_writing_answer)+(SELECT count(*) FROM ielts_legacy.ielts_gn_listening_answer)
        +(SELECT count(*) FROM ielts_legacy.ielts_gn_reading_answer)+(SELECT count(*) FROM ielts_legacy.ielts_gn_writing_answer)'),
    'score_entries' => (int)$one('SELECT (SELECT count(*) FROM ielts_legacy.ielts_academic_scores)+(SELECT count(*) FROM ielts_legacy.ielts_general_scores)
        +(SELECT count(*) FROM ielts_legacy.esl_scores)+(SELECT count(*) FROM ielts_legacy.business_scores)') - count($unmatched),
    'schedules' => (int)$one('SELECT count(*) FROM ielts_legacy.student_schedules') - count($schedSkip),
];
$ok = true;
foreach ($expect as $t => $n) {
    $got = (int)$one("SELECT count(*) FROM $t");
    printf("%-14s legacy=%d app=%d %s\n", $t, $n, $got, $got === $n ? 'OK' : 'MISMATCH');
    $ok = $ok && $got === $n;
}
printf("placeholder users (attempts without account): %d\n", $orphans);
printf("unmatched score rows: %s | skipped schedules: %s\n", implode(', ', $unmatched) ?: '-', implode(', ', $schedSkip) ?: '-');
printf("graded L/R attempts: %d, no key: %d, score differs from row's stored key: %d (avg app %.2f vs row-key %.2f)\n",
    $stats['rows'], $stats['nokey'], $stats['diff_vs_rowkey'], $stats['sum_app'] / max(1, $stats['rows']), $stats['sum_rowkey'] / max(1, $stats['rows']));
if (!$ok) { $pdo->rollBack(); fwrite(STDERR, "ROLLBACK\n"); exit(1); }
$pdo->commit();
echo "COMMIT OK\n";
