<?php
// 모든 .php 요청의 입구 — Vercel(vercel-php 런타임)과 로컬 개발 서버가 같이 씀.
//   로컬:   php -S 127.0.0.1:8080 api/index.php
//   Vercel: vercel.json 의 routes 가 .php 요청을 여기로 보냄 (원래 주소는 REQUEST_URI 에 그대로 남음)
// 원본 경로(admin/scores.php, Courses/Ielts/... 등)를 실제 파일로 연결하고, 소스·DB 폴더는 막음.

$__root = dirname(__DIR__);
$__path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

// 내부 폴더·숨김 파일 차단
if (preg_match('#^/(data|db|includes|api|uploads)(/|$)|/_engine(/|$)|/\.|\.\.#', $__path)) {
    http_response_code(403);
    exit('Forbidden');
}

// 로컬 개발 서버: 정적 파일(css/js/이미지)은 그대로 제공
if (PHP_SAPI === 'cli-server' && !str_ends_with($__path, '.php') && is_file($__root . $__path)) {
    return false;
}

// Courses/Ielts/{academic|general}/{SET}/{listening|reading|writing}/{file}
if (preg_match('#^/Courses/Ielts/(academic|general)/((?:ACADEMIC|GENERAL)\d+)/(listening|reading|writing)/(.+)$#', $__path, $__m)) {
    $__engine = $__root . '/Courses/Ielts/_engine/';
    $__file = $__m[4];
    if (preg_match('#^(images|audio)/[\w.-]+$#', $__file) && is_file($__engine . $__file)) {
        $__types = ['svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'wav' => 'audio/wav', 'mp3' => 'audio/mpeg'];
        header('Content-Type: ' . ($__types[pathinfo($__file, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
        header('Cache-Control: public, max-age=86400');
        readfile($__engine . $__file);
        exit;
    }
    if (preg_match('#^\w+\.php$#', $__file) && is_file($__engine . $__file)
        && !in_array($__file, ['context.php', 'exam.php', 'sectioninfo.php'], true)) {
        $_GET['kind'] = $__m[1];
        $_GET['set'] = $__m[2];
        $_GET['section'] = $__m[3];
        chdir($__engine);
        require $__engine . $__file;
        exit;
    }
    http_response_code(404);
    exit('Not found');
}

if ($__path === '/' || str_ends_with($__path, '/')) {
    $__path .= 'index.php';
}

if (preg_match('#^/[\w/-]+\.php$#', $__path)) {
    $__file = realpath($__root . $__path);
    if ($__file && str_starts_with($__file, $__root . '/') && is_file($__file)) {
        $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = $__path;
        $_SERVER['SCRIPT_FILENAME'] = $__file;
        chdir(dirname($__file));
        require $__file;
        exit;
    }
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'Not found';
