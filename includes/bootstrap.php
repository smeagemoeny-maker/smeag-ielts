<?php
// 공통 초기화: 세션, DB, 인증, 상수, 채점 함수
declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

define('APP_ROOT', dirname(__DIR__));
define('ON_VERCEL', (bool)getenv('VERCEL'));

/** 설정이 빠졌을 때 안내 화면 (Vercel 에서 DB 환경변수 없음 등) */
function setup_error(string $msg): never
{
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="ko"><head><meta charset="UTF-8"><title>SMEAG e-Test 설정 필요</title></head>'
        . '<body style="font-family:Arial,sans-serif;max-width:640px;margin:60px auto;padding:0 16px;color:#222">'
        . '<h1 style="color:#e74c3c">SMEAG</h1><h2>서버 설정이 필요합니다</h2><p>' . htmlspecialchars($msg) . '</p>'
        . '<p style="color:#888">README.md 의 “Vercel 배포” 항목을 참고하세요.</p></body></html>';
    exit;
}

// ---------- DB ----------
/** [dsn, user, pass] — 우선순위: SMEAG_DSN > DATABASE_URL(Postgres URI) > 로컬 SQLite */
function db_config(): array
{
    if ($dsn = getenv('SMEAG_DSN')) {
        return [$dsn, getenv('SMEAG_DB_USER') ?: null, getenv('SMEAG_DB_PASS') ?: null];
    }
    foreach (['SMEAG_DATABASE_URL', 'DATABASE_URL', 'POSTGRES_URL'] as $key) {
        $url = getenv($key);
        if (!$url) continue;
        $p = parse_url($url);
        if (!$p || !in_array($p['scheme'] ?? '', ['postgres', 'postgresql'], true) || empty($p['host'])) {
            setup_error("$key 값이 postgresql://user:password@host:port/dbname 형식이 아닙니다.");
        }
        parse_str($p['query'] ?? '', $qs);
        $local = in_array($p['host'], ['localhost', '127.0.0.1'], true);
        $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s;sslmode=%s', $p['host'], $p['port'] ?? 5432,
            ltrim($p['path'] ?? '/postgres', '/') ?: 'postgres', $qs['sslmode'] ?? ($local ? 'prefer' : 'require'));
        return [$dsn, isset($p['user']) ? urldecode($p['user']) : null, isset($p['pass']) ? urldecode($p['pass']) : null];
    }
    if (ON_VERCEL) {
        setup_error('DATABASE_URL 환경변수가 없습니다. Vercel 프로젝트 Settings → Environment Variables 에 Supabase 접속 주소(Session pooler URI)를 넣고 다시 배포하세요.');
    }
    return ['sqlite:' . APP_ROOT . '/data/smeag.sqlite', null, null];
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;

    [$dsn, $user, $pass] = db_config();
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            // Supabase pooler(pgbouncer) 에서도 동작하도록 서버측 prepared statement 사용 안 함
            PDO::ATTR_EMULATE_PREPARES => str_starts_with($dsn, 'pgsql:'),
        ]);
    } catch (PDOException $e) {
        $why = $e->getMessage();
        if ($pass !== null && $pass !== '') $why = str_replace($pass, '****', $why);
        error_log('DB connect failed: ' . $why);
        preg_match('/host=([^;]*)/', $dsn, $hm);
        setup_error('데이터베이스에 연결할 수 없습니다. DATABASE_URL(호스트·비밀번호)을 확인하세요. '
            . '[host=' . ($hm[1] ?? '?') . ', user=' . ($user ?? '?') . '] ' . $why);
    }
    if (db_driver($pdo) === 'sqlite') {
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    ensure_schema($pdo);
    return $pdo;
}

function db_driver(?PDO $pdo = null): string
{
    return ($pdo ?? db())->getAttribute(PDO::ATTR_DRIVER_NAME);
}

/** 테이블이 없으면 만들고, 사용자가 한 명도 없으면 초기 데이터 입력 */
function ensure_schema(PDO $pdo): void
{
    $driver = db_driver($pdo);
    // 평소에는 쿼리 1번으로 끝냄 (테이블 있고 사용자 있음)
    try {
        if ($pdo->query('SELECT 1 FROM users LIMIT 1')->fetchColumn()) {
            return;
        }
    } catch (PDOException) {
        // 테이블이 아직 없음 → 생성 (모두 IF NOT EXISTS)
        $pdo->exec(file_get_contents(APP_ROOT . ($driver === 'pgsql' ? '/db/schema_pgsql.sql' : '/db/schema.sql')));
    }

    require_once APP_ROOT . '/db/seed.php';
    $pdo->beginTransaction();
    if ($driver === 'pgsql') {
        $pdo->query('SELECT pg_advisory_xact_lock(717171)');   // 동시에 여러 서버가 시작해도 한 번만 입력
    }
    if ((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
        seed_database($pdo);
    }
    $pdo->commit();
}

// ---------- 세션 (DB 저장) ----------
final class DbSessionHandler implements SessionHandlerInterface
{
    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }

    public function read(string $id): string|false
    {
        $row = q('SELECT data FROM sessions WHERE id = ?', [$id])->fetch();
        return $row ? (string)$row['data'] : '';
    }

    public function write(string $id, string $data): bool
    {
        q('INSERT INTO sessions (id, data, updated_at) VALUES (?, ?, ?)
           ON CONFLICT (id) DO UPDATE SET data = excluded.data, updated_at = excluded.updated_at', [$id, $data, time()]);
        return true;
    }

    public function destroy(string $id): bool
    {
        q('DELETE FROM sessions WHERE id = ?', [$id]);
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        return q('DELETE FROM sessions WHERE updated_at < ?', [time() - $max_lifetime])->rowCount();
    }
}


function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function log_activity(string $user, string $action, string $details): void
{
    q('INSERT INTO activity_log (user_label, action, details, created_at) VALUES (?,?,?,?)', [$user, $action, $details, now()]);
}

// ---------- 인증 ----------
function current_user(): ?array
{
    if (empty($_SESSION['uid'])) return null;
    static $u = null;
    if ($u === null) {
        $u = q('SELECT * FROM users WHERE id = ?', [$_SESSION['uid']])->fetch() ?: null;
    }
    return $u;
}

/** $base: 현재 파일에서 앱 루트까지 상대경로 (예: '../') */
function require_login(string $base = ''): array
{
    $u = current_user();
    if (!$u) {
        header('Location: ' . $base . 'login.php');
        exit;
    }
    try { regrade_general_listening_once(); } catch (Throwable $e) { error_log('regrade: ' . $e->getMessage()); }
    return $u;
}

function require_admin(string $base = '../'): array
{
    $u = require_login($base);
    if ($u['user_type'] !== 'admin') {
        header('Location: ' . $base . 'student/index.php');
        exit;
    }
    return $u;
}

// ---------- 상수 ----------
const SCORE_TYPES = ['ESL TEST', 'TOEIC TEST', 'TOEFL TEST', 'IELTS ACADEMIC TEST', 'IELTS GENERAL TEST', 'BUSINESS TEST', 'SPEAKING MASTER TEST'];
const SUBTABS = [
    'ESL TEST'             => ['Listening', 'Reading', 'Writing', 'Speaking'],
    'TOEIC TEST'           => ['Listening', 'Reading'],
    'TOEFL TEST'           => ['Listening', 'Reading', 'Writing', 'Speaking'],
    'IELTS ACADEMIC TEST'  => ['Listening', 'Reading', 'Writing'],
    'IELTS GENERAL TEST'   => ['Listening', 'Reading', 'Writing'],
    'BUSINESS TEST'        => ['Listening', 'Reading', 'Writing', 'Speaking'],
    'SPEAKING MASTER TEST' => ['Listening', 'Reading', 'Writing', 'Speaking'],
];
// 응시 데이터가 있는 시험 (탭 이름 → test_attempts.test_type)
const DATA_TYPES = ['IELTS ACADEMIC TEST' => 'IELTS ACADEMIC', 'IELTS GENERAL TEST' => 'IELTS GENERAL'];
const INDATA_TYPES = ['ESL', 'TOEIC', 'TOEFL', 'IELTS ACADEMIC', 'IELTS GENERAL', 'BUSINESS'];
const COURSES = ['ESL', 'TOEIC', 'TOEFL', 'SPEAKING MASTER', 'BUSINESS', 'IELTS ACADEMIC', 'IELTS GENERAL'];
const CAMPUSES = ['CAPITAL', 'ENCANTO', 'TARLAC', 'BAYDREAM'];
const SCHEDULE_TYPES = ['IELTS ACADEMIC', 'IELTS GENERAL', 'TOEIC', 'TOEFL', 'ESL', 'BUSINESS'];
const SET_COUNT = 30;

/** 예전 철자 TOFLE 로 저장된 점수·일정을 TOEFL 로 바꿈 (관리자 indata·schedule 화면을 열 때. 바꿀 줄이 없으면 아무 일 없음) */
function fix_toefl_spelling(): void
{
    q("UPDATE score_entries SET test_type = 'TOEFL' WHERE test_type = 'TOFLE'");
    q("UPDATE schedules SET test_type = 'TOEFL' WHERE test_type = 'TOFLE'");
}

// ---------- 채점 (Listening · Reading 모든 세트) ----------
// 정답 칸 쓰는 법:
//   '//' 또는 ' / '      허용 답 구분           3 // three hours,  6:30(pm) / 6:30
//   붙여 쓴 '/'          그 낱말만 바꿔도 정답   88 Prince's Street/St.,  8/eight   (12/05 처럼 숫자/숫자는 그대로)
//   ( )                  있어도 없어도 정답      6:30(pm)
//   'any two'            목록 중 두 개 (두 칸 문항)   course // flights // camera any two
// 비교할 때: 대소문자·구두점·공백·하이픈 무시, 숫자 낱말은 숫자로 (three = 3, four-week = 4-week)
function normalize_answer(string $s): string
{
    $s = mb_strtolower(trim(stripslashes($s)));
    $s = str_replace(['’', '‘', '“', '”'], ["'", "'", '"', '"'], $s);
    $s = preg_replace("/[\"'!?;,.()]/u", '', $s);
    $s = preg_replace('/\s+/u', ' ', $s);
    return trim($s);
}

/** 비교용 모양: normalize + 숫자 낱말 → 숫자 + 공백·하이픈 없앰 */
function answer_canon(string $s): string
{
    static $num = ['zero' => 0, 'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'seven' => 7,
        'eight' => 8, 'nine' => 9, 'ten' => 10, 'eleven' => 11, 'twelve' => 12, 'thirteen' => 13, 'fourteen' => 14,
        'fifteen' => 15, 'sixteen' => 16, 'seventeen' => 17, 'eighteen' => 18, 'nineteen' => 19, 'twenty' => 20,
        'thirty' => 30, 'forty' => 40, 'fifty' => 50, 'sixty' => 60, 'seventy' => 70, 'eighty' => 80, 'ninety' => 90];
    $s = normalize_answer($s);
    $s = preg_replace_callback('/\b(' . implode('|', array_keys($num)) . ')\b/u', fn($m) => (string)$num[$m[1]], $s);
    return preg_replace('/[\s\-‐–—]+/u', '', $s);
}

/** 'any two' 같은 안내 문구 → 골라야 할 개수 (없으면 0) */
function answer_pick_count(string $key): int
{
    return preg_match('/\bany\s+(two|2)\b/iu', $key) ? 2 : (preg_match('/\bany\s+(three|3)\b/iu', $key) ? 3 : 0);
}

/** 정답 칸 → 허용 답 목록 (//, ' / ', 붙여 쓴 a/b, (선택) 모두 펼침) */
function answer_alternatives(string $key): array
{
    $key = preg_replace('/\(?\s*\b(in\s+)?any\s+(order|two|2|three|3)\b\s*\)?/iu', '', $key);
    $out = [];
    foreach (preg_split('#\s*//\s*|\s+/\s+#u', $key) as $alt) {
        $alt = trim($alt);
        if ($alt === '') continue;
        // 붙여 쓴 '/' (Street/St., 8/eight): 그 낱말을 하나씩 바꾼 모양들 + 원래 모양(km/h). 숫자/숫자(날짜·분수)는 그대로
        $out[] = $alt;
        if (str_contains($alt, '(')) $out[] = trim(preg_replace('/\s*\([^)]*\)\s*/u', ' ', $alt));
        $vars = [''];
        foreach (preg_split('/(\s+)/u', $alt, -1, PREG_SPLIT_DELIM_CAPTURE) as $tok) {
            $opts = (str_contains($tok, '/') && !preg_match('#\d/\d#u', $tok))
                ? array_values(array_filter(explode('/', $tok), fn($x) => $x !== '')) : [$tok];
            $next = [];
            foreach ($vars as $v) foreach ($opts as $o) $next[] = $v . $o;
            $vars = array_slice($next, 0, 32);
        }
        foreach ($vars as $v) {
            $out[] = trim($v);
            // ( ) 안은 있어도 없어도 정답
            if (str_contains($v, '(')) $out[] = trim(preg_replace('/\s*\([^)]*\)\s*/u', ' ', $v));
        }
    }
    return array_values(array_unique(array_filter($out, fn($a) => answer_canon($a) !== '')));
}

function is_correct(?string $given, ?string $key): bool
{
    $given = (string)$given;
    if (normalize_answer($given) === '' || $key === null || trim($key) === '') return false;
    $alts = answer_alternatives($key);
    $canonAlts = array_map('answer_canon', $alts);
    // 답 여러 개 문항 (q30[] → "a,b"): 순서 무관, "," / "and" / "&" 구분 모두 허용
    $split = fn(string $s): array => array_values(array_filter(
        preg_split('/\s*[,&]\s*|\s+and\s+/iu', $s), fn($x) => answer_canon($x) !== ''));

    // 'any two': 목록에서 서로 다른 답을 정해진 개수만큼 (하나만 쓰면 오답)
    if ($pick = answer_pick_count($key)) {
        $parts = $split($given);
        if (count($parts) !== $pick) return false;
        $used = [];
        foreach ($parts as $p) {
            $c = answer_canon($p);
            $hit = false;
            foreach ($canonAlts as $i => $a) {
                if ($a === $c && !isset($used[$a])) { $used[$a] = true; $hit = true; break; }
            }
            if (!$hit) return false;
        }
        return true;
    }

    $g = answer_canon($given);
    if (in_array($g, $canonAlts, true)) return true;
    // 정답 하나에 두 낱말 ("bread and milk" ↔ "milk, bread")
    $gp = array_map('answer_canon', $split($given));
    if (count($gp) > 1) {
        sort($gp);
        foreach ($alts as $alt) {
            $ap = array_map('answer_canon', $split($alt));
            sort($ap);
            if ($ap === $gp) return true;
        }
    }
    return false;
}

/** 'ACADEMIC 18' → 'ACADEMIC18' (answer_keys.test_name 형식) */
function key_name(string $testSet): string
{
    return str_replace(' ', '', strtoupper($testSet));
}

function answer_key(string $testSet, string $section): ?array
{
    $name = key_name($testSet);
    $sec = strtoupper($section);
    // GENERAL N 듣기 = ACADEMIC N 듣기 (같은 음원·같은 문제) → ACADEMIC N 정답을 씀. 없으면 GENERAL N 자기 정답
    $names = $sec === 'LISTENING' && preg_match('/^GENERAL(\d+)$/', $name, $m) ? ['ACADEMIC' . $m[1], $name] : [$name];
    foreach ($names as $n) {
        $row = q('SELECT answers FROM answer_keys WHERE UPPER(test_name) = ? AND UPPER(course) = ? ORDER BY id DESC LIMIT 1',
            [$n, $sec])->fetch();
        if ($row) return json_decode($row['answers'], true);
    }
    return null;
}

/** 저장된 점수를 지금 정답으로 다시 채점 (test_set 예: 'GENERAL 19', 'GENERAL19' 모두 됨) → 바뀐 행 수 */
function regrade_attempts(string $testSet, string $section): int
{
    $sec = strtoupper($section);
    $key = answer_key($testSet, $sec);
    if (!$key) return 0;
    $changed = 0;
    $rows = q("SELECT id, answers, score FROM test_attempts WHERE REPLACE(UPPER(test_set), ' ', '') = ? AND UPPER(section) = ?",
        [key_name($testSet), $sec])->fetchAll();
    foreach ($rows as $r) {
        $given = json_decode((string)$r['answers'], true);
        if (!is_array($given)) continue;
        $score = grade($given, $key);
        if ($score !== (int)$r['score']) {
            q('UPDATE test_attempts SET score = ? WHERE id = ?', [$score, $r['id']]);
            $changed++;
        }
    }
    return $changed;
}

/** 한 번만: GENERAL 듣기 응시를 ACADEMIC 듣기 정답으로 다시 채점 (예전엔 GENERAL 정답이 없어 0점) */
function regrade_general_listening_once(): void
{
    if (!empty($_SESSION['regraded_gl_v1'])) return;
    $mark = 'regrade-general-listening-v1';
    if (!q("SELECT 1 FROM activity_log WHERE action = 'System' AND details = ? LIMIT 1", [$mark])->fetchColumn()) {
        log_activity('system', 'System', $mark);   // 먼저 남겨서 동시에 여러 번 돌지 않게
        $sets = q("SELECT DISTINCT test_set FROM test_attempts WHERE UPPER(section) = 'LISTENING' AND UPPER(test_set) LIKE 'GENERAL%'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($sets as $s) regrade_attempts((string)$s, 'LISTENING');
    }
    $_SESSION['regraded_gl_v1'] = true;
}

function grade(array $given, ?array $key): int
{
    if (!$key) return 0;
    $score = 0;
    for ($i = 0; $i < 40; $i++) {
        if (is_correct($given[$i] ?? '', $key[$i] ?? '')) $score++;
    }
    return $score;
}

function word_count(?string $s): int
{
    $s = trim((string)$s);
    return $s === '' ? 0 : count(preg_split('/\s+/u', $s));
}

/** 세트 이름을 숫자 순으로 정렬 (원본은 1, 10, 11 … 문자열 정렬이었음) */
function natural_sets(string $prefix): array
{
    $out = [];
    for ($i = 1; $i <= SET_COUNT; $i++) $out[] = "$prefix $i";
    return $out;
}

/** 현재 GET 값을 유지하며 일부만 바꾼 쿼리스트링 */
function qs(array $override): string
{
    $p = array_merge($_GET, $override);
    $p = array_filter($p, fn($v) => $v !== null && $v !== '');
    return '?' . http_build_query($p);
}

function json_out($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------- 세션 시작 (상수·함수가 모두 정의된 뒤 — 첫 요청 때 DB 초기 데이터 입력이 여기서 일어날 수 있음) ----------
if (session_status() === PHP_SESSION_NONE) {
    $https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    session_set_save_handler(new DbSessionHandler(), true);
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $https]);
    ini_set('session.gc_maxlifetime', '28800');   // 8시간
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '100');
    session_start();
}
