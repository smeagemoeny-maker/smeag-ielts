-- SMEAG e-Test 스키마 (SQLite 기본. MySQL로 옮길 때는 INTEGER PRIMARY KEY AUTOINCREMENT → INT AUTO_INCREMENT PRIMARY KEY)
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
  submitted_at  DATETIME NOT NULL
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
