<?php
// 초기 데이터 (가상의 학생 — 실제 학생 정보 아님). 사용자 테이블이 비어 있을 때 bootstrap.php 가 자동 실행 (트랜잭션은 호출하는 쪽에서).
// 로컬 다시 만들기: data/smeag.sqlite 삭제 후 아무 페이지나 열기
// 관리자 비밀번호: 환경변수 SMEAG_ADMIN_PASSWORD (Vercel 에서는 필수), 없으면 로컬 기본값 smeag1234

/** 여러 행을 한 번에 INSERT (원격 DB 왕복 횟수 줄이기) */
function bulk_insert(PDO $pdo, string $table, array $cols, array $rows, int $chunk = 100): void
{
    foreach (array_chunk($rows, $chunk) as $part) {
        $ph = '(' . implode(',', array_fill(0, count($cols), '?')) . ')';
        $sql = "INSERT INTO $table (" . implode(',', $cols) . ') VALUES ' . implode(',', array_fill(0, count($part), $ph));
        $pdo->prepare($sql)->execute(array_merge(...array_map('array_values', $part)));
    }
}

function seed_database(PDO $pdo): void
{
    $adminPw = getenv('SMEAG_ADMIN_PASSWORD') ?: null;
    if (!$adminPw) {
        if (ON_VERCEL) {
            $pdo->rollBack();
            setup_error('SMEAG_ADMIN_PASSWORD 환경변수가 없습니다. 관리자(A2025001) 첫 비밀번호로 쓸 값을 Vercel 환경변수에 넣고 다시 배포하세요.');
        }
        $adminPw = 'smeag1234';
    }
    mt_srand(20251113);

    $userCols = ['login_id', 'password_hash', 'ename', 'gname', 'birth', 'gender', 'phone', 'course', 'user_type', 'campus', 'created_at'];
    $users = [['A2025001', password_hash($adminPw, PASSWORD_DEFAULT), 'sunny', 'Sunny', '1990-01-01', 'Female',
        '010-0000-0000', 'IELTS ACADEMIC', 'admin', 'CAPITAL', '2025-01-02 09:00:00']];

    // 학생 (가상의 이름, 비밀번호 student1234)
    $studentHash = password_hash('student1234', PASSWORD_DEFAULT);
    $names = ['ALEX KIM', 'BELLA PARK', 'CHRIS LEE', 'DANA CHOI', 'ELLA JUNG', 'FINN KANG', 'GRACE YOON', 'HANNAH LIM',
        'IAN HAN', 'JUSTIN OH', 'KYRA SEO', 'LEO SHIN', 'MIA KWON', 'NOAH HWANG', 'OLIVIA AHN', 'PAUL SONG', 'QUINN YOO',
        'RYAN JANG', 'SOPHIE NAM', 'TOM BAEK', 'UMA HONG', 'VICTOR JEON', 'WENDY KO', 'XAVIER MOON', 'YUNA SON',
        'ZOE YANG', 'AIKO TANAKA', 'KENJI SATO', 'MEI CHEN', 'LUCAS WANG', 'NINA IVANOVA', 'OSCAR MENDEZ'];
    $students = [];
    foreach ($names as $i => $name) {
        $n = $i + 1;
        $course = $n % 4 === 0 ? 'IELTS GENERAL' : 'IELTS ACADEMIC';
        if ($n % 9 === 0) $course = 'TOEIC';
        $login = sprintf('s2025%03d', $n);
        $users[] = [$login, $studentHash, $name, ucwords(strtolower(explode(' ', $name)[0])),
            sprintf('200%d-0%d-1%d', $n % 10, ($n % 9) + 1, $n % 10), $n % 2 ? 'Male' : 'Female',
            sprintf('010-%04d-%04d', 1000 + $n, 2000 + $n), $course, 'student', CAMPUSES[$n % 4],
            date('Y-m-d H:i:s', strtotime("2025-03-01 +$n days"))];
        $students[] = ['login' => $login, 'course' => $course];
    }
    bulk_insert($pdo, 'users', $userCols, $users);
    $ids = $pdo->query('SELECT login_id, id FROM users')->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($students as &$s) $s['id'] = (int)$ids[$s['login']];
    unset($s);

    // 정답 (ACADEMIC 1~30, GENERAL 1~30 × Listening/Reading = 120건)
    $wordPool = ['Johnstone', 'Marina', 'Hong Kong', 'clothes', 'name', 'fly', 'sense of smell', 'introduced animals',
        'Global education', 'Sydney', 'Frankfurt', 'commodity crop', 'Edward Lloyd', 'New York', 'captain // team captain',
        '9:30 // 9:30 am', 'about 10 // 10', 'a penny // 1 penny // one penny', 'North Africa // Africa', 'life jackets'];
    $mc = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
    $tfng = ['TRUE', 'FALSE', 'NOT GIVEN'];
    $keyRows = [];
    $keys = [];
    $t = strtotime('2025-04-23 10:37:00');
    foreach (['ACADEMIC', 'GENERAL'] as $prefix) {
        for ($n = 1; $n <= SET_COUNT; $n++) {
            foreach (['Listening', 'Reading'] as $course) {
                $ans = [];
                for ($q = 1; $q <= 40; $q++) {
                    if ($course === 'Reading' && $q <= 13) $ans[] = $tfng[mt_rand(0, 2)];
                    elseif ($q % 5 === 0) $ans[] = $wordPool[mt_rand(0, count($wordPool) - 1)];
                    else $ans[] = $mc[mt_rand(0, 7)];
                }
                $keyRows[] = ["$prefix$n", $course, json_encode($ans, JSON_UNESCAPED_UNICODE), date('Y-m-d H:i:s', $t)];
                $keys["$prefix $n|" . strtoupper($course)] = $ans;
                $t += 86400 + mt_rand(0, 7200);
            }
        }
    }
    bulk_insert($pdo, 'answer_keys', ['test_name', 'course', 'answers', 'created_at'], $keyRows);

    // 응시 기록 (학생별 1~4회 × Listening/Reading/Writing)
    $task1 = "The diagram illustrates how chocolate is produced and how the price of a chocolate bar is divided between those involved in the process.\n\nOverall, the largest share goes to the cost of ingredients and overheads, while farmers receive the smallest share.";
    $task2 = "Many people treat dogs and cats like family members. I believe animals can be protected by both their owners and society.\n\nOwners give them food, water and shelter. In addition, local communities can run campaigns to reduce the number of stray animals.";
    $attRows = [];
    $base = strtotime('2026-08-01 09:00:00');
    foreach ($students as $s) {
        if (!str_starts_with($s['course'], 'IELTS')) continue;
        $prefix = $s['course'] === 'IELTS GENERAL' ? 'GENERAL' : 'ACADEMIC';
        $times = mt_rand(1, 4);
        for ($k = 0; $k < $times; $k++) {
            $set = $prefix . ' ' . mt_rand(1, SET_COUNT);
            $ts = $base + mt_rand(0, 100) * 86400 + mt_rand(0, 7200);
            foreach (['LISTENING', 'READING'] as $sec) {
                $key = $keys["$set|$sec"];
                $given = [];
                foreach ($key as $a) {
                    $alts = answer_alternatives($a);
                    $given[] = mt_rand(1, 100) <= 65 ? $alts[0] : (mt_rand(0, 1) ? $mc[mt_rand(0, 7)] : '');
                }
                $attRows[] = [$s['id'], $s['course'], $set, $sec, json_encode($given, JSON_UNESCAPED_UNICODE),
                    null, null, grade($given, $key), date('Y-m-d H:i:s', $ts)];
                $ts += 1800 + mt_rand(0, 300);
            }
            $attRows[] = [$s['id'], $s['course'], $set, 'WRITING', null, $task1, $task2, 0, date('Y-m-d H:i:s', $ts + 3600)];
        }
    }
    bulk_insert($pdo, 'test_attempts', ['user_id', 'test_type', 'test_set', 'section', 'answers', 'writing_task1', 'writing_task2', 'score', 'submitted_at'], $attRows);

    // 교사 점수
    $adminId = (int)$ids['A2025001'];
    $scoreRows = [];
    foreach (array_slice($students, 0, 8) as $s) {
        $scoreRows[] = [$s['id'], $s['course'], '2026-09-01', '6.5', '6.0', '5.5', 'Good progress. Focus on Writing Task 2 structure.', $adminId, '2026-09-01 18:00:00'];
    }
    bulk_insert($pdo, 'score_entries', ['user_id', 'test_type', 'test_date', 'listening_score', 'reading_score', 'writing_score', 'sa_teacher_comment', 'updated_by', 'created_at'], $scoreRows);

    // 일정
    bulk_insert($pdo, 'schedules', ['student_id', 'test_date', 'test_time', 'test_subject', 'test_type', 'location', 'notes', 'created_at'],
        [[$students[10]['id'], '2025-07-11', '16:17:00', 'first test', 'IELTS ACADEMIC', '6floor 304', 'good', '2025-07-01 09:00:00']]);

    // 최근 활동
    bulk_insert($pdo, 'activity_log', ['user_label', 'action', 'details', 'created_at'], [
        ['s2025002', 'Registered', 'Joined ENCANTO campus', '2024-06-30 10:00:00'],
        ['A2025001', 'Updated', 'Changed course info', '2024-07-01 11:00:00'],
        ['s2025001', 'Registered', 'Joined CAPITAL campus', '2024-07-01 12:00:00'],
    ]);
}
