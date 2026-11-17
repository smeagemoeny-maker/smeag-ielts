-- SMEAG e-Test 스키마 (SQLite — 로컬 개발용. Postgres/Supabase 는 schema_pgsql.sql)
-- 원본은 시험×영역마다 테이블이 따로 있었으나, 여기서는 test_type / section 컬럼으로 합침.

CREATE TABLE IF NOT EXISTS users (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  login_id      VARCHAR(50)  NOT NULL UNIQUE,   -- A2025001, s2025001, RM-61
  password_hash VARCHAR(255) NOT NULL,
  ename         VARCHAR(100) NOT NULL,          -- English Name (화면 표시 이름)
  gname         VARCHAR(100),                   -- Global Name
  birth         DATE,
  gender        VARCHAR(10),
  phone         VARCHAR(30),
  course        VARCHAR(30),                    -- ESL / TOEIC / TOEFL / SPEAKING MASTER / BUSINESS / IELTS ACADEMIC / IELTS GENERAL
  user_type     VARCHAR(10)  NOT NULL DEFAULT 'student',  -- admin / student
  campus        VARCHAR(30),                    -- CAPITAL / ENCANTO / TARLAC / BAYDREAM
  photo         VARCHAR(255),
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- answer.php : 정답 등록 (test, course, c1~c40)
CREATE TABLE IF NOT EXISTS answer_keys (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  test_name   VARCHAR(50) NOT NULL,             -- ACADEMIC18, GENERAL30
  course      VARCHAR(20) NOT NULL,             -- Listening / Reading / Writing
  answers     TEXT NOT NULL,                    -- JSON 배열 40칸. 허용답 여러 개는 "a // b"
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_answer_keys_test ON answer_keys(test_name, course);

-- 응시 1회 × 영역 1개 (Scores / Test Check / indata Check Answer)
CREATE TABLE IF NOT EXISTS test_attempts (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id       INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  test_type     VARCHAR(30) NOT NULL,           -- IELTS ACADEMIC / IELTS GENERAL
  test_set      VARCHAR(50) NOT NULL,           -- ACADEMIC 18
  section       VARCHAR(20) NOT NULL,           -- LISTENING / READING / WRITING
  answers       TEXT,                           -- JSON 배열 40칸 (학생 답)
  writing_task1 TEXT,
  writing_task2 TEXT,
  score         INTEGER DEFAULT 0,
  submitted_at  DATETIME NOT NULL,
  leave_count   INTEGER,                        -- 시험 화면을 떠난 횟수 (NULL = 기록 전 응시)
  fs_exit_count INTEGER                         -- 전체 화면을 나간 횟수 (Esc 등, NULL = 기록 전 응시)
);
CREATE INDEX IF NOT EXISTS idx_attempts_type ON test_attempts(test_type, section, submitted_at);
CREATE INDEX IF NOT EXISTS idx_attempts_user ON test_attempts(user_id);

-- indata.php : 교사 점수/코멘트
CREATE TABLE IF NOT EXISTS score_entries (
  id                 INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id            INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  test_type          VARCHAR(30) NOT NULL,      -- ESL / TOEIC / TOFLE / IELTS ACADEMIC / IELTS GENERAL / BUSINESS
  test_date          DATE NOT NULL,
  listening_score    VARCHAR(10),
  reading_score      VARCHAR(10),
  speaking_score     VARCHAR(10),
  writing_score      VARCHAR(10),
  sa_teacher_comment TEXT,
  updated_by         INTEGER,
  created_at         DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- schedule.php
CREATE TABLE IF NOT EXISTS schedules (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  student_id   INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  test_date    DATE NOT NULL,
  test_time    TIME NOT NULL,
  test_subject VARCHAR(100),
  test_type    VARCHAR(30),
  location     VARCHAR(100),
  notes        TEXT,
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- admin_page.php Recent Activity
CREATE TABLE IF NOT EXISTS activity_log (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  user_label VARCHAR(50),
  action     VARCHAR(50),
  details    VARCHAR(255),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 로그인 세션 (DB 저장 — Vercel 같은 서버리스에서도 로그인 유지)
CREATE TABLE IF NOT EXISTS sessions (
  id         VARCHAR(128) PRIMARY KEY,
  data       TEXT NOT NULL,
  updated_at INTEGER NOT NULL
);

-- 응시 중 답안 자동 저장 (정전·인터넷 끊김 대비). 제출하면 지움. 기존 DB 에는 exam.php 가 처음 쓸 때 만듦
CREATE TABLE IF NOT EXISTS exam_drafts (
  user_id    INTEGER NOT NULL,
  test_set   VARCHAR(30) NOT NULL,
  section    VARCHAR(20) NOT NULL,
  answers    TEXT NOT NULL,
  started_at INTEGER NOT NULL,
  updated_at INTEGER NOT NULL,
  elapsed    INTEGER NOT NULL DEFAULT 0,   -- 시험 화면이 켜져 있던 시간(초) — 정전 동안은 세지 않음
  audio_pos  REAL NOT NULL DEFAULT 0,      -- Listening 음원 위치(초)
  single_mode INTEGER NOT NULL DEFAULT 0,  -- Individual Skill Test
  leave_count INTEGER NOT NULL DEFAULT 0,  -- 시험 화면을 떠난 횟수
  fs_exit_count INTEGER NOT NULL DEFAULT 0, -- 전체 화면을 나간 횟수
  PRIMARY KEY (user_id, test_set, section)
);
