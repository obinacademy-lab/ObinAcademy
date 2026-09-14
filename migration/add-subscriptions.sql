-- Subscription model (Go/Plus/Pro) — run this before deploying the code
-- that depends on it. Safe to run once against the live database; not
-- idempotent (re-running will error on already-created tables/columns).

CREATE TABLE subscriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tier ENUM('GO','PLUS','PRO') NOT NULL,
  status ENUM('ACTIVE','GRACE','EXPIRED','CANCELED') NOT NULL DEFAULT 'ACTIVE',
  price DECIMAL(12,2) NOT NULL,
  phone VARCHAR(32) NOT NULL,
  current_period_ends_at DATETIME NOT NULL,
  grace_attempts_made TINYINT NOT NULL DEFAULT 0,
  last_charge_attempt_at DATETIME NULL,
  referred_by_affiliate_id INT NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  canceled_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  user_id INT NOT NULL UNIQUE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (referred_by_affiliate_id) REFERENCES affiliates(id) ON DELETE SET NULL,
  INDEX idx_subscriptions_status_period (status, current_period_ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE payments
  MODIFY COLUMN course_id INT NULL,
  MODIFY COLUMN type ENUM('COURSE_PURCHASE','PREMIUM_UPGRADE','SUBSCRIPTION') NOT NULL DEFAULT 'COURSE_PURCHASE',
  ADD COLUMN subscription_id INT NULL AFTER course_id,
  ADD COLUMN subscription_tier ENUM('GO','PLUS','PRO') NULL AFTER subscription_id,
  ADD FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL;

ALTER TABLE affiliate_earnings
  MODIFY COLUMN course_id INT NULL;

CREATE TABLE lesson_watch_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  seconds_watched INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  user_id INT NOT NULL,
  lesson_id INT NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
  INDEX idx_watch_events_lesson_time (lesson_id, created_at),
  INDEX idx_watch_events_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE lesson_text_completions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  credit_seconds INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  user_id INT NOT NULL,
  lesson_id INT NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_user_lesson (user_id, lesson_id),
  INDEX idx_text_completions_lesson_time (lesson_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE creator_subscription_payouts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  period_month DATE NOT NULL,
  watch_seconds BIGINT NOT NULL DEFAULT 0,
  platform_total_watch_seconds BIGINT NOT NULL DEFAULT 0,
  pool_amount DECIMAL(12,2) NOT NULL,
  payout_amount DECIMAL(12,2) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  creator_id INT NOT NULL,
  FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_period_creator (period_month, creator_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
