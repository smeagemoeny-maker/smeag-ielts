# SMEAG e-Test (재구현)

`../smeag_etest_clone/SPEC.md` 분석서를 기준으로 원본(`http://1.234.23.54/smeag_etest/`)과 같은 구조로 다시 만든 PHP 웹앱.
원본 CSS(`css/panel-style.css`, `css/pages/*.inline.css`)는 그대로 사용.

## 실행

```bash
cd smeag_etest
php -S 127.0.0.1:8080 api/index.php
# → http://127.0.0.1:8080
```

- PHP 8.1 이상 + pdo_sqlite (macOS: `brew install php`)
- `api/index.php` 가 모든 `.php` 요청의 입구 (Vercel 과 로컬이 같은 파일 사용)
- DB: 환경변수 `DATABASE_URL`(Postgres URI)이 있으면 Postgres, 없으면 `data/smeag.sqlite` (자동 생성)
- 첫 접속 때 테이블 생성 + 초기 데이터 입력 (사용자가 0명일 때만)
- 로컬 데이터 초기화: `rm data/smeag.sqlite` 후 아무 페이지나 열기
- Apache 배포: `.htaccess` 에 같은 규칙이 들어 있음 (mod_rewrite 필요)

## Vercel 배포 (https://smeag-ielts.vercel.app)

`vercel.json` 이 PHP 런타임(`vercel-php@0.9.0`)을 쓰고, `.php`·`db/`·`includes/` 는 소스가 그대로 내려가지 않도록 막음.
GitHub `main` 에 push 하면 자동 배포됨. Vercel 은 디스크가 유지되지 않으므로 **Postgres(Supabase)가 반드시 필요**:

Vercel 프로젝트 → Settings → Environment Variables (Production·Preview 모두):

| 이름 | 값 |
|---|---|
| `DATABASE_URL` | Supabase → Connect → **Session pooler** URI. 예: `postgresql://postgres.<프로젝트ID>:<DB비밀번호>@aws-0-<지역>.pooler.supabase.com:5432/postgres` |
| `SMEAG_ADMIN_PASSWORD` | 관리자 `A2025001` 의 첫 비밀번호 (처음 데이터 입력 때 한 번만 사용) |

넣은 뒤 Deployments → 최신 배포 → Redeploy. 첫 접속 때 테이블(`db/schema_pgsql.sql`)과 초기 데이터가 자동으로 들어감.
값이 빠져 있으면 사이트에 "서버 설정이 필요합니다" 안내가 뜸. 로그인 세션도 DB(`sessions` 테이블)에 저장됨.
Supabase 테이블에는 RLS 를 켜 두어 공개 API 키로는 읽을 수 없음 (PHP 서버만 접근).

## 레거시 데이터 이관 (원본 MySQL `smeag_etest` → Supabase)

1. 원본 19개 테이블을 Supabase 의 `ielts_legacy` 스키마에 그대로 복사 (원본 보존용, RLS 켬·공개 키 접근 없음)
2. `db/migrate_legacy.php` 로 앱 테이블(users·answer_keys·test_attempts·score_entries·schedules)로 변환

```bash
SMEAG_DATABASE_URL='postgresql://postgres:<pw>@db.<프로젝트ID>.supabase.co:5432/postgres' php db/migrate_legacy.php            # 앱 테이블이 비어 있을 때
SMEAG_DATABASE_URL='...' php db/migrate_legacy.php --replace  # 앱 테이블을 비우고 다시 채움 (앱에서 새로 생긴 기록도 지워짐)
```

- 비밀번호는 원본 bcrypt 그대로 → 기존 비밀번호로 로그인
- 점수는 앱 채점 함수(`includes/bootstrap.php` 의 `grade()`)로 다시 계산 → 원본이 다른 세트 정답으로 채점한 행은 점수가 달라짐
- 응시 기록만 있고 회원 정보가 없는 ID 는 로그인 불가 계정(`password_hash = '!legacy-no-account'`)으로 만들어 기록 보존
- 한 트랜잭션 — 원본 건수와 맞지 않으면 아무것도 바꾸지 않음

## 테스트 계정 (더미)

| 역할 | ID | PW |
|---|---|---|
| 관리자 | A2025001 | 로컬: smeag1234 / Vercel: `SMEAG_ADMIN_PASSWORD` 값 |
| 학생 | s2025001 ~ s2025032 | student1234 |

## 구조 (원본 경로와 같음)

```
smeag_etest/
├─ api/index.php       모든 .php 요청의 입구 (Vercel·로컬 공통 라우터)
├─ vercel.json
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
│     → 실제 파일은 Courses/Ielts/_engine/ 하나 (api/index.php / .htaccess 가 연결)
│       listninginfo.php(원본 철자) → listeningcorse.php → reading → writing → done.php
├─ student/index.php   학생 홈 (세트 선택 · 내 성적 · 일정)
├─ includes/           bootstrap.php (DB·인증·채점), layout.php (sidebar·topbar)
├─ db/                 schema.sql (SQLite) · schema_pgsql.sql (Postgres) · seed.php
├─ css/ js/ data/
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
