-- Lets a creator allow a learner to pay for one course in a fixed number of
-- installments instead of all at once. Full access unlocks after the FIRST
-- installment (mobile money has no saved-token recurring charge, so full
-- gating on every payment would mean re-locking a paying learner out
-- constantly) — remaining installments are collected via reminders, with a
-- grace period before access pauses if one is missed. See
-- includes/installments.php.
ALTER TABLE courses
  ADD COLUMN installments_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER access_duration_days,
  ADD COLUMN installment_count TINYINT NULL AFTER installments_enabled;

CREATE TABLE installment_plans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  status ENUM('ACTIVE','GRACE','COMPLETED','DEFAULTED') NOT NULL DEFAULT 'ACTIVE',
  total_amount DECIMAL(12,2) NOT NULL,
  installment_count TINYINT NOT NULL,
  installment_amount DECIMAL(12,2) NOT NULL,
  installments_paid TINYINT NOT NULL DEFAULT 0,
  phone VARCHAR(32) NOT NULL,
  next_due_at DATETIME NOT NULL,
  grace_ends_at DATETIME NULL,
  -- Set when cron/track-maintenance.php emails a "next installment due"
  -- reminder for the CURRENT installment, so the sweep doesn't re-email
  -- every run — reset back to NULL every time a payment succeeds.
  reminder_sent_at DATETIME NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  learner_id INT NOT NULL,
  creator_id INT NOT NULL,
  course_id INT NOT NULL,
  FOREIGN KEY (learner_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_learner_course_plan (learner_id, course_id),
  INDEX idx_installment_plans_status_due (status, next_due_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE payments
  MODIFY COLUMN type ENUM('COURSE_PURCHASE','PREMIUM_UPGRADE','SUBSCRIPTION','SCHOOL_SUBSCRIPTION','BUNDLE_PURCHASE','INSTALLMENT_PAYMENT') NOT NULL DEFAULT 'COURSE_PURCHASE',
  ADD COLUMN installment_plan_id INT NULL AFTER bundle_id,
  ADD FOREIGN KEY (installment_plan_id) REFERENCES installment_plans(id) ON DELETE SET NULL;
