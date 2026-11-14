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
        error_log('DB connect failed: ' . $e->getMessage());
        setup_error('데이터베이스에 연결할 수 없습니다. DATABASE_URL(호스트·비밀번호)을 확인하세요.');
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
    try {
        $pdo->query('SELECT 1 FROM sessions LIMIT 1');
    } catch (PDOException) {
        // 테이블이 아직 없음 → 생성 (모두 IF NOT EXISTS)
        $pdo->exec(file_get_contents(APP_ROOT . ($driver === 'pgsql' ? '/db/schema_pgsql.sql' : '/db/schema.sql')));
    }
    if ($pdo->query('SELECT 1 FROM users LIMIT 1')->fetchColumn()) {
        return;
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
const SCORE_TYPES = ['ESL TEST', 'TOEIC TEST', 'TOFLE TEST', 'IELTS ACADEMIC TEST', 'IELTS GENERAL TEST', 'BUSINESS TEST', 'SPEAKING MASTER TEST'];
const SUBTABS = [
    'ESL TEST'             => ['Listening', 'Reading', 'Writing', 'Speaking'],
    'TOEIC TEST'           => ['Listening', 'Reading'],
    'TOFLE TEST'           => ['Listening', 'Reading', 'Writing', 'Speaking'],
    'IELTS ACADEMIC TEST'  => ['Listening', 'Reading', 'Writing'],
    'IELTS GENERAL TEST'   => ['Listening', 'Reading', 'Writing'],
    'BUSINESS TEST'        => ['Listening', 'Reading', 'Writing', 'Speaking'],
    'SPEAKING MASTER TEST' => ['Listening', 'Reading', 'Writing', 'Speaking'],
];
// 응시 데이터가 있는 시험 (탭 이름 → test_attempts.test_type)
const DATA_TYPES = ['IELTS ACADEMIC TEST' => 'IELTS ACADEMIC', 'IELTS GENERAL TEST' => 'IELTS GENERAL'];
const INDATA_TYPES = ['ESL', 'TOEIC', 'TOFLE', 'IELTS ACADEMIC', 'IELTS GENERAL', 'BUSINESS'];
const COURSES = ['ESL', 'TOEIC', 'TOEFL', 'SPEAKING MASTER', 'BUSINESS', 'IELTS ACADEMIC', 'IELTS GENERAL'];
const CAMPUSES = ['CAPITAL', 'ENCANTO', 'TARLAC', 'BAYDREAM'];
const SCHEDULE_TYPES = ['IELTS ACADEMIC', 'IELTS GENERAL', 'TOEIC', 'TOFLE', 'ESL', 'BUSINESS'];
const SET_COUNT = 30;

// ---------- 채점 ----------
// 정답 문자열의 '//' 또는 ' / ' 는 허용 답 구분. 비교 전 소문자·공백·구두점 정리 (원본은 엄격 비교였음)
function normalize_answer(string $s): string
{
    $s = mb_strtolower(trim(stripslashes($s)));
    $s = str_replace(['’', '‘', '“', '”'], ["'", "'", '"', '"'], $s);
    $s = preg_replace("/[\"'!?;,.()]/u", '', $s);
    $s = preg_replace('/\s+/u', ' ', $s);
    return trim($s);
}

function answer_alternatives(string $key): array
{
    return array_values(array_filter(array_map('trim', preg_split('#\s*//\s*|\s+/\s+#u', $key)), fn($a) => $a !== ''));
}

function is_correct(?string $given, ?string $key): bool
{
    $g = normalize_answer((string)$given);
    if ($g === '' || $key === null || trim($key) === '') return false;
    foreach (answer_alternatives($key) as $alt) {
        if ($g === normalize_answer($alt)) return true;
        // 공백 차이만 있는 숫자 (0414 847 749 vs 0414847749)
        if (str_replace(' ', '', $g) === str_replace(' ', '', normalize_answer($alt))) return true;
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
    $row = q('SELECT answers FROM answer_keys WHERE UPPER(test_name) = ? AND UPPER(course) = ? ORDER BY id DESC LIMIT 1',
        [key_name($testSet), strtoupper($section)])->fetch();
    return $row ? json_decode($row['answers'], true) : null;
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
