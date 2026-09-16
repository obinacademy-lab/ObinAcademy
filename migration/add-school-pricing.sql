-- Lets each creator's school choose its own pricing model: charge per
-- course (unchanged default) or a monthly subscription for all-access to
-- everything that creator publishes. school_subscriptions is a new table,
-- not a repurposed old `subscriptions` (which is UNIQUE per user platform-
-- wide — wrong shape here, since a learner can subscribe to several
-- different schools at once).
ALTER TABLE users
  ADD COLUMN pricing_model ENUM('PER_COURSE','MONTHLY_SUBSCRIPTION') NOT NULL DEFAULT 'PER_COURSE' AFTER school_cover_url,
  ADD COLUMN school_monthly_price DECIMAL(12,2) NULL AFTER pricing_model;

CREATE TABLE school_subscriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  status ENUM('ACTIVE','GRACE','EXPIRED','CANCELED') NOT NULL DEFAULT 'ACTIVE',
  price DECIMAL(12,2) NOT NULL,
  phone VARCHAR(32) NOT NULL,
  current_period_ends_at DATETIME NOT NULL,
  grace_ends_at DATETIME NULL,
  renewal_attempts_made TINYINT NOT NULL DEFAULT 0,
  last_charge_attempt_at DATETIME NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  canceled_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  learner_id INT NOT NULL,
  creator_id INT NOT NULL,
  FOREIGN KEY (learner_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_learner_creator (learner_id, creator_id),
  INDEX idx_school_subs_status_period (status, current_period_ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE payments
  MODIFY COLUMN type ENUM('COURSE_PURCHASE','PREMIUM_UPGRADE','SUBSCRIPTION','SCHOOL_SUBSCRIPTION') NOT NULL DEFAULT 'COURSE_PURCHASE',
  ADD COLUMN school_subscription_id INT NULL AFTER subscription_tier,
  ADD FOREIGN KEY (school_subscription_id) REFERENCES school_subscriptions(id) ON DELETE SET NULL;
