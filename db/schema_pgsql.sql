-- SMEAG e-Test 스키마 (PostgreSQL / Supabase)
-- 날짜·시간은 SQLite 판과 같은 쿼리를 쓰도록 'YYYY-MM-DD HH:MM:SS' 문자열로 저장

CREATE TABLE IF NOT EXISTS users (
  id            SERIAL PRIMARY KEY,
  login_id      VARCHAR(50)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  ename         VARCHAR(100) NOT NULL,
  gname         VARCHAR(100),
  birth         VARCHAR(10),
  gender        VARCHAR(10),
  phone         VARCHAR(30),
  course        VARCHAR(30),
  user_type     VARCHAR(10)  NOT NULL DEFAULT 'student',
  campus        VARCHAR(30),
  photo         TEXT,
  created_at    VARCHAR(19) DEFAULT to_char(now(), 'YYYY-MM-DD HH24:MI:SS')
);

CREATE TABLE IF NOT EXISTS answer_keys (
  id          SERIAL PRIMARY KEY,
  test_name   VARCHAR(50) NOT NULL,
  course      VARCHAR(20) NOT NULL,
  answers     TEXT NOT NULL,
  created_at  VARCHAR(19) DEFAULT to_char(now(), 'YYYY-MM-DD HH24:MI:SS')
);
CREATE INDEX IF NOT EXISTS idx_answer_keys_test ON answer_keys(test_name, course);

CREATE TABLE IF NOT EXISTS test_attempts (
  id            SERIAL PRIMARY KEY,
  user_id       INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  test_type     VARCHAR(30) NOT NULL,
  test_set      VARCHAR(50) NOT NULL,
  section       VARCHAR(20) NOT NULL,
  answers       TEXT,
  writing_task1 TEXT,
  writing_task2 TEXT,
  score         INTEGER DEFAULT 0,
  submitted_at  VARCHAR(19) NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_attempts_type ON test_attempts(test_type, section, submitted_at);
CREATE INDEX IF NOT EXISTS idx_attempts_user ON test_attempts(user_id);

CREATE TABLE IF NOT EXISTS score_entries (
  id                 SERIAL PRIMARY KEY,
  user_id            INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  test_type          VARCHAR(30) NOT NULL,
  test_date          VARCHAR(10) NOT NULL,
  listening_score    VARCHAR(10),
  reading_score      VARCHAR(10),
  speaking_score     VARCHAR(10),
  writing_score      VARCHAR(10),
  sa_teacher_comment TEXT,
  updated_by         INTEGER,
  created_at         VARCHAR(19) DEFAULT to_char(now(), 'YYYY-MM-DD HH24:MI:SS')
);

CREATE TABLE IF NOT EXISTS schedules (
  id           SERIAL PRIMARY KEY,
  student_id   INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  test_date    VARCHAR(10) NOT NULL,
  test_time    VARCHAR(8) NOT NULL,
  test_subject VARCHAR(100),
  test_type    VARCHAR(30),
  location     VARCHAR(100),
  notes        TEXT,
  created_at   VARCHAR(19) DEFAULT to_char(now(), 'YYYY-MM-DD HH24:MI:SS')
);

CREATE TABLE IF NOT EXISTS activity_log (
  id         SERIAL PRIMARY KEY,
  user_label VARCHAR(50),
  action     VARCHAR(50),
  details    VARCHAR(255),
  created_at VARCHAR(19) DEFAULT to_char(now(), 'YYYY-MM-DD HH24:MI:SS')
);

-- 로그인 세션 (서버리스: 서버가 여러 대여도 유지되도록 DB에 저장)
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
  PRIMARY KEY (user_id, test_set, section)
);

-- Supabase: 이 테이블들은 PHP 서버만 접근. 공개 API(anon 키)로는 읽지 못하게 RLS 켜 둠 (정책 없음 = 전부 거부)
ALTER TABLE users ENABLE ROW LEVEL SECURITY;
ALTER TABLE answer_keys ENABLE ROW LEVEL SECURITY;
ALTER TABLE test_attempts ENABLE ROW LEVEL SECURITY;
ALTER TABLE score_entries ENABLE ROW LEVEL SECURITY;
ALTER TABLE schedules ENABLE ROW LEVEL SECURITY;
ALTER TABLE activity_log ENABLE ROW LEVEL SECURITY;
ALTER TABLE sessions ENABLE ROW LEVEL SECURITY;
ALTER TABLE exam_drafts ENABLE ROW LEVEL SECURITY;
