# SMEAG e-Test (재구현)

`../smeag_etest_clone/SPEC.md` 분석서를 기준으로 원본(`http://1.234.23.54/smeag_etest/`)과 같은 구조로 다시 만든 PHP 웹앱.
원본 CSS(`css/panel-style.css`, `css/pages/*.inline.css`)는 그대로 사용.

## 실행

```bash
cd smeag_etest
php -S 127.0.0.1:8080 router.php
# → http://127.0.0.1:8080
```

- PHP 8.1 이상 + pdo_sqlite (macOS: `brew install php`)
- 첫 접속 때 `data/smeag.sqlite` 가 자동 생성되고 더미 데이터가 들어감
- 데이터 초기화: `rm data/smeag.sqlite` 후 아무 페이지나 열기
- MySQL 사용: 환경변수 `SMEAG_DSN`, `SMEAG_DB_USER`, `SMEAG_DB_PASS` (스키마는 `db/schema.sql`, AUTOINCREMENT 문법만 바꾸면 됨)
- Apache 배포: `.htaccess` 에 `router.php` 와 같은 규칙이 들어 있음 (mod_rewrite 필요)

## 테스트 계정 (더미)

| 역할 | ID | PW |
|---|---|---|
| 관리자 | A2025001 | smeag1234 |
| 학생 | s2025001 ~ s2025032 | student1234 |

## 구조 (원본 경로와 같음)

```
smeag_etest/
├─ index.php / login.php / register.php / logout.php
├─ admin/
│  ├─ admin_page.php   Home
│  ├─ etest.php        English TEST
│  ├─ scores.php       Scores
│  ├─ answer.php       Answer
│  ├─ indata.php       indata (점수 모달 · INFO · PDF · Check Answer)
│  ├─ Check.php        Test Check
│  ├─ schedule.php     Schedule
│  ├─ student_info_api.php · get_dates.php · get_answer_dates.php · get_answer_detail.php   (JSON)
│  └─ pdf.php          성적표 (브라우저 인쇄 → PDF 저장)
├─ Courses/Ielts/{academic|general}/{ACADEMIC|GENERAL}{N}/{listening|reading|writing}/*.php
│     → 실제 파일은 Courses/Ielts/_engine/ 하나 (router.php / .htaccess 가 연결)
│       listninginfo.php(원본 철자) → listeningcorse.php → reading → writing → done.php
├─ student/index.php   학생 홈 (세트 선택 · 내 성적 · 일정)
├─ includes/           bootstrap.php (DB·인증·채점), layout.php (sidebar·topbar)
├─ css/ js/ db/ data/ uploads/
```

## 세트별 실제 시험 자료 넣기

시험지·음원은 원본에서 확인하지 못해 비어 있음. 세트 폴더에 파일을 두면 자동으로 사용:

```
Courses/Ielts/academic/ACADEMIC1/listening/audio/listening.mp3   ← Listening 음원
Courses/Ielts/academic/ACADEMIC1/listening/content.html          ← 문제지 (HTML 조각)
Courses/Ielts/academic/ACADEMIC1/reading/content.html
```

정답은 관리자 **Answer** 메뉴에서 Test Name `ACADEMIC1`, Course `Listening` 처럼 등록 (허용 답 여러 개는 `a // b`).

## 원본과 다르게 한 것 (SPEC.md "같이 고치면 좋은 원본 문제")

- 사이드바 active 표시를 현재 페이지에 맞춤 (원본은 거의 항상 Home)
- 상단 이메일 칸을 모든 페이지에서 이름으로 통일
- 세트 번호 숫자 순 정렬 (원본: 1, 10, 11 …)
- INFO 버튼 동작 (원본: 500 오류)
- Test Check: 누구의 결과인지 표시, Previous/Next 로 응시 기록 이동, ESL 등 탭은 무한 로딩 대신 "Database table not found"
- 일정 수정 시 학생 선택 유지, 삭제 확인창
- 채점: 대소문자·공백·구두점 차이 무시 (원본은 엄격 비교), `\'` 이중 이스케이프 없음
- Home 통계·Recent Activity 를 DB 값으로
- 회원가입에서 Admin 은 관리자 로그인 상태에서만 생성 가능 (보안)
- 응시 채점은 서버에서 (정답을 페이지에 넣지 않음), 시간 초과 제출은 무효 처리

## 원본에서 확인 못 해 새로 설계한 것

실제 응시 화면(`listeningcorse.php` 등), 학생 로그인 후 화면, PDF 양식, 저장 후 메시지 문구.
상단 공식 시험 로고(IELTS 등)는 `Courses/Ielts/_engine/images/` 의 임시 이미지 — 사용 권한이 있는 로고로 바꾸면 됨.
