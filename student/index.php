<?php
// 학생 로그인 후 첫 화면 — 관리자 English TEST 와 같은 코스 카드 화면 (내 IELTS 세트 목록은 처음부터 열림)
require_once __DIR__ . '/../includes/student_layout.php';
$me = require_student();

student_head('home', 'Home - SMEAG');
student_courses($me);
student_foot();
