<?php
// 개발 서버용 라우터:  php -S localhost:8080 router.php   (smeag_etest 폴더에서 실행)
// Apache 에서는 같은 규칙이 .htaccess 에 있음.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// 내부 폴더 차단
if (preg_match('#^/(data|db|includes)(/|$)#', $path) || str_contains($path, '/_engine/')) {
    http_response_code(403);
    exit('Forbidden');
}

// Courses/Ielts/{academic|general}/{SET}/{section}/{file}
if (preg_match('#^/Courses/Ielts/(academic|general)/((?:ACADEMIC|GENERAL)\d+)/(listening|reading|writing)/(.+)$#', $path, $m)) {
    // 실제 파일(세트별 음원·이미지·문제지)이 있으면 그대로 제공
    if (is_file(__DIR__ . $path) && !str_ends_with($path, '.php')) return false;

    $engine = __DIR__ . '/Courses/Ielts/_engine/';
    $file = $m[4];
    if (preg_match('#^(images|audio)/[\w.-]+$#', $file) && is_file($engine . $file)) {
        $types = ['svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'wav' => 'audio/wav', 'mp3' => 'audio/mpeg'];
        header('Content-Type: ' . ($types[pathinfo($file, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
        readfile($engine . $file);
        return true;
    }
    if (preg_match('#^\w+\.php$#', $file) && is_file($engine . $file) && !in_array($file, ['context.php', 'exam.php', 'sectioninfo.php'], true)) {
        $_GET['kind'] = $m[1];
        $_GET['set'] = $m[2];
        $_GET['section'] = $m[3];
        chdir($engine);
        require $engine . $file;
        return true;
    }
    http_response_code(404);
    exit('Not found');
}

if ($path === '/') {
    require __DIR__ . '/index.php';
    return true;
}
return false;
