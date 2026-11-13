<?php
// 공통 초기화: 세션, DB, 인증, 상수, 채점 함수
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Manila');

define('APP_ROOT', dirname(__DIR__));

// ---------- DB ----------
function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;

    // MySQL 사용 시: SMEAG_DSN="mysql:host=127.0.0.1;dbname=smeag_etest;charset=utf8mb4" SMEAG_DB_USER=... SMEAG_DB_PASS=...
    $dsn = getenv('SMEAG_DSN') ?: 'sqlite:' . APP_ROOT . '/data/smeag.sqlite';
    $isNew = str_starts_with($dsn, 'sqlite:') && !file_exists(substr($dsn, 7));

    $pdo = new PDO($dsn, getenv('SMEAG_DB_USER') ?: null, getenv('SMEAG_DB_PASS') ?: null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    if (str_starts_with($dsn, 'sqlite:')) {
        $pdo->exec('PRAGMA foreign_keys = ON');
        if ($isNew) {
            $pdo->exec(file_get_contents(APP_ROOT . '/db/schema.sql'));
            require_once APP_ROOT . '/db/seed.php';
            seed_database($pdo);
        }
    }
    return $pdo;
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
