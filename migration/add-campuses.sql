-- Campuses: year-long programmes taught by professors Obin Academy appoints and pays.
-- Learners pay per campus, monthly (30 days of access) or yearly (12 months). Nothing renews by itself:
-- mobile money has no saved-card charge, so access simply ends and the learner pays again.
-- Additive only. Creators' courses, enrollments and payments are untouched.

CREATE TABLE IF NOT EXISTS campuses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  tagline VARCHAR(255) NOT NULL DEFAULT '',
  description TEXT NULL,
  icon VARCHAR(20) NOT NULL DEFAULT 'biz',
  monthly_price DECIMAL(12,2) NOT NULL DEFAULT 100000,
  yearly_price DECIMAL(12,2) NOT NULL DEFAULT 1000000,
  professor_name VARCHAR(120) NULL,
  professor_title VARCHAR(160) NULL,
  professor_bio TEXT NULL,
  professor_photo_url VARCHAR(500) NULL,
  professor_fee_monthly DECIMAL(12,2) NULL,
  live_schedule VARCHAR(120) NULL,
  status ENUM('DRAFT','PUBLISHED') NOT NULL DEFAULT 'DRAFT',
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per month of the year (1-12).
CREATE TABLE IF NOT EXISTS campus_stages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  campus_id INT NOT NULL,
  month_no TINYINT NOT NULL,
  title VARCHAR(160) NOT NULL DEFAULT '',
  summary VARCHAR(255) NOT NULL DEFAULT '',
  UNIQUE KEY uniq_campus_month (campus_id, month_no),
  CONSTRAINT fk_campus_stages_campus FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS campus_lessons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  campus_id INT NOT NULL,
  month_no TINYINT NOT NULL,
  title VARCHAR(200) NOT NULL,
  kind ENUM('VIDEO','PDF') NOT NULL DEFAULT 'VIDEO',
  file_url VARCHAR(500) NOT NULL,
  file_name VARCHAR(255) NULL,
  duration_label VARCHAR(40) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_campus_lessons_month (campus_id, month_no, sort_order),
  CONSTRAINT fk_campus_lessons_campus FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS campus_live_classes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  campus_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  starts_at DATETIME NOT NULL,
  duration_minutes INT NOT NULL DEFAULT 90,
  join_url VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_campus_live_starts (campus_id, starts_at),
  CONSTRAINT fk_campus_live_campus FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per learner per campus. Access is open while access_until is in the future.
CREATE TABLE IF NOT EXISTS campus_memberships (
  id INT AUTO_INCREMENT PRIMARY KEY,
  campus_id INT NOT NULL,
  user_id INT NOT NULL,
  plan ENUM('MONTHLY','YEARLY') NOT NULL,
  started_at DATETIME NOT NULL,
  access_until DATETIME NOT NULL,
  last_payment_id INT NULL,
  last_reminder_days TINYINT NULL,
  last_reminder_at DATETIME NULL,
  reminders_off TINYINT(1) NOT NULL DEFAULT 0,
  certificate_code VARCHAR(20) NULL UNIQUE,
  certificate_issued_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_campus_member (campus_id, user_id),
  KEY idx_campus_members_until (access_until),
  CONSTRAINT fk_campus_members_campus FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE CASCADE,
  CONSTRAINT fk_campus_members_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS campus_lesson_progress (
  user_id INT NOT NULL,
  lesson_id INT NOT NULL,
  completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, lesson_id),
  CONSTRAINT fk_campus_progress_lesson FOREIGN KEY (lesson_id) REFERENCES campus_lessons(id) ON DELETE CASCADE,
  CONSTRAINT fk_campus_progress_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The campus community: one shared room per campus.
CREATE TABLE IF NOT EXISTS campus_posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  campus_id INT NOT NULL,
  user_id INT NOT NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_campus_posts (campus_id, id),
  CONSTRAINT fk_campus_posts_campus FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE CASCADE,
  CONSTRAINT fk_campus_posts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- What Obin Academy owes / has paid each campus professor, per month (YYYY-MM). Tracking only.
CREATE TABLE IF NOT EXISTS campus_payouts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  campus_id INT NOT NULL,
  period CHAR(7) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  paid_at DATETIME NULL,
  note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_campus_period (campus_id, period),
  CONSTRAINT fk_campus_payouts_campus FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Payments: a new kind, plus which campus and plan it was for.
ALTER TABLE payments
  MODIFY COLUMN type ENUM('COURSE_PURCHASE','PREMIUM_UPGRADE','SUBSCRIPTION','SCHOOL_SUBSCRIPTION','BUNDLE_PURCHASE','INSTALLMENT_PAYMENT','COURSE_GIFT','CAMPUS_MEMBERSHIP') NOT NULL DEFAULT 'COURSE_PURCHASE',
  ADD COLUMN campus_id INT NULL,
  ADD COLUMN campus_plan ENUM('MONTHLY','YEARLY') NULL;

-- The seven launch campuses, as drafts: nothing is public until the admin adds a professor and content and publishes.
INSERT IGNORE INTO campuses (slug, name, tagline, icon, sort_order) VALUES
('business-finance', 'Business & Finance', 'Pricing, cash flow, profit and growing a business that lasts.', 'biz', 1),
('crypto-trading', 'Crypto Trading', 'Reading the market, managing risk and trading with a written plan.', 'crypto', 2),
('forex-trading', 'Forex Trading', 'Charts, sessions, risk and a strategy you can test.', 'fx', 3),
('content-creation', 'Content Creation', 'Growing an audience and turning views into income.', 'content', 4),
('fitness-nutrition', 'Fitness & Nutrition', 'Training, eating and recovering well, built around Ugandan life.', 'fit', 5),
('ai-technology', 'AI & Technology', 'Working faster with AI and selling digital services.', 'ai', 6),
('e-commerce', 'E-Commerce', 'Finding products, building a store and delivering across Uganda.', 'shop', 7);
