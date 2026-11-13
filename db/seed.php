<?php
// 개발용 더미 데이터 (실제 학생 정보 아님). DB 파일이 없을 때 bootstrap.php 가 자동 실행.
// 다시 만들기: data/smeag.sqlite 삭제 후 아무 페이지나 열기

function seed_database(PDO $pdo): void
{
    mt_srand(20251113);
    $pdo->beginTransaction();

    $insUser = $pdo->prepare('INSERT INTO users (login_id, password_hash, ename, gname, birth, gender, phone, course, user_type, campus, created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)');

    // 관리자
    $insUser->execute(['A2025001', password_hash('smeag1234', PASSWORD_DEFAULT), 'sunny', 'Sunny', '1990-01-01', 'Female',
        '010-0000-0000', 'IELTS ACADEMIC', 'admin', 'CAPITAL', '2025-01-02 09:00:00']);

    // 학생 (가상의 이름)
    $names = ['ALEX KIM', 'BELLA PARK', 'CHRIS LEE', 'DANA CHOI', 'ELLA JUNG', 'FINN KANG', 'GRACE YOON', 'HANNAH LIM',
        'IAN HAN', 'JUSTIN OH', 'KYRA SEO', 'LEO SHIN', 'MIA KWON', 'NOAH HWANG', 'OLIVIA AHN', 'PAUL SONG', 'QUINN YOO',
        'RYAN JANG', 'SOPHIE NAM', 'TOM BAEK', 'UMA HONG', 'VICTOR JEON', 'WENDY KO', 'XAVIER MOON', 'YUNA SON',
        'ZOE YANG', 'AIKO TANAKA', 'KENJI SATO', 'MEI CHEN', 'LUCAS WANG', 'NINA IVANOVA', 'OSCAR MENDEZ'];
    $students = [];
    foreach ($names as $i => $name) {
        $n = $i + 1;
        $course = $n % 4 === 0 ? 'IELTS GENERAL' : 'IELTS ACADEMIC';
        if ($n % 9 === 0) $course = 'TOEIC';
        $insUser->execute([sprintf('s2025%03d', $n), password_hash('student1234', PASSWORD_DEFAULT), $name,
            ucwords(strtolower(explode(' ', $name)[0])), sprintf('200%d-0%d-1%d', $n % 10, ($n % 9) + 1, $n % 10),
            $n % 2 ? 'Male' : 'Female', sprintf('010-%04d-%04d', 1000 + $n, 2000 + $n), $course, 'student',
            CAMPUSES[$n % 4], date('Y-m-d H:i:s', strtotime("2025-03-01 +$n days"))]);
        $students[] = ['id' => (int)$pdo->lastInsertId(), 'course' => $course, 'ename' => $name];
    }

    // 정답 (ACADEMIC 1~30, GENERAL 1~30 × Listening/Reading = 120건)
    $wordPool = ['Johnstone', 'Marina', 'Hong Kong', 'clothes', 'name', 'fly', 'sense of smell', 'introduced animals',
        'Global education', 'Sydney', 'Frankfurt', 'commodity crop', 'Edward Lloyd', 'New York', 'captain // team captain',
        '9:30 // 9:30 am', 'about 10 // 10', 'a penny // 1 penny // one penny', 'North Africa // Africa', 'life jackets'];
    $mc = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
    $tfng = ['TRUE', 'FALSE', 'NOT GIVEN'];
    $insKey = $pdo->prepare('INSERT INTO answer_keys (test_name, course, answers, created_at) VALUES (?,?,?,?)');
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
                $insKey->execute(["$prefix$n", $course, json_encode($ans, JSON_UNESCAPED_UNICODE), date('Y-m-d H:i:s', $t)]);
                $keys["$prefix $n|" . strtoupper($course)] = $ans;
                $t += 86400 + mt_rand(0, 7200);
            }
        }
    }

    // 응시 기록 (학생별 1~4회 × Listening/Reading/Writing)
    $task1 = "The diagram illustrates how chocolate is produced and how the price of a chocolate bar is divided between those involved in the process.\n\nOverall, the largest share goes to the cost of ingredients and overheads, while farmers receive the smallest share.";
    $task2 = "Many people treat dogs and cats like family members. I believe animals can be protected by both their owners and society.\n\nOwners give them food, water and shelter. In addition, local communities can run campaigns to reduce the number of stray animals.";
    $insAtt = $pdo->prepare('INSERT INTO test_attempts (user_id, test_type, test_set, section, answers, writing_task1, writing_task2, score, submitted_at)
        VALUES (?,?,?,?,?,?,?,?,?)');
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
                $insAtt->execute([$s['id'], $s['course'], $set, $sec, json_encode($given, JSON_UNESCAPED_UNICODE),
                    null, null, grade($given, $key), date('Y-m-d H:i:s', $ts)]);
                $ts += 1800 + mt_rand(0, 300);
            }
            $insAtt->execute([$s['id'], $s['course'], $set, 'WRITING', null, $task1, $task2, 0, date('Y-m-d H:i:s', $ts + 3600)]);
        }
    }

    // 교사 점수
    $insScore = $pdo->prepare('INSERT INTO score_entries (user_id, test_type, test_date, listening_score, reading_score, writing_score, sa_teacher_comment, updated_by, created_at)
        VALUES (?,?,?,?,?,?,?,1,?)');
    foreach (array_slice($students, 0, 8) as $s) {
        $insScore->execute([$s['id'], $s['course'], '2026-09-01', '6.5', '6.0', '5.5', 'Good progress. Focus on Writing Task 2 structure.', '2026-09-01 18:00:00']);
    }

    // 일정
    $pdo->prepare('INSERT INTO schedules (student_id, test_date, test_time, test_subject, test_type, location, notes) VALUES (?,?,?,?,?,?,?)')
        ->execute([$students[10]['id'], '2025-07-11', '16:17:00', 'first test', 'IELTS ACADEMIC', '6floor 304', 'good']);

    // 최근 활동
    $insLog = $pdo->prepare('INSERT INTO activity_log (user_label, action, details, created_at) VALUES (?,?,?,?)');
    $insLog->execute(['s2025002', 'Registered', 'Joined ENCANTO campus', '2024-06-30 10:00:00']);
    $insLog->execute(['A2025001', 'Updated', 'Changed course info', '2024-07-01 11:00:00']);
    $insLog->execute(['s2025001', 'Registered', 'Joined CAPITAL campus', '2024-07-01 12:00:00']);

    $pdo->commit();
}
