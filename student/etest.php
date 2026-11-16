<?php
// 학생 English TEST — Home 과 같은 코스 선택 화면
require_once __DIR__ . '/../includes/student_layout.php';
$me = require_student();

student_head('etest', 'English TEST - SMEAG');
student_courses($me);
student_foot();
