-- Professor pay: each professor earns a share (40% by default) of every campus payment, the moment
-- it succeeds. Earnings are recorded per payment; the admin pays them out by mobile money or bank and
-- records the reference, which marks those earnings as paid. Also: applications to teach a campus.
-- Additive only. Run after migration/add-campuses.sql.

ALTER TABLE campuses
  ADD COLUMN IF NOT EXISTS professor_share_percent DECIMAL(5,2) NOT NULL DEFAULT 40.00 AFTER professor_fee_monthly,
  ADD COLUMN IF NOT EXISTS professor_user_id INT NULL AFTER professor_share_percent,
  ADD COLUMN IF NOT EXISTS professor_payout_phone VARCHAR(32) NULL AFTER professor_user_id,
  ADD COLUMN IF NOT EXISTS professor_payout_name VARCHAR(120) NULL AFTER professor_payout_phone;

-- One row per successful campus payment: what the professor earned from it.
CREATE TABLE IF NOT EXISTS campus_professor_earnings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  campus_id INT NOT NULL,
  payment_id INT NOT NULL,
  learner_id INT NULL,
  professor_name VARCHAR(120) NULL,
  professor_user_id INT NULL,
  plan ENUM('MONTHLY','YEARLY') NOT NULL DEFAULT 'MONTHLY',
  gross DECIMAL(12,2) NOT NULL,
  share_percent DECIMAL(5,2) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  payout_id INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_professor_earning_payment (payment_id),
  KEY idx_professor_earnings_campus (campus_id, payout_id),
  CONSTRAINT fk_professor_earnings_campus FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Money actually sent to a professor. Each payout settles a set of earnings (earnings.payout_id).
CREATE TABLE IF NOT EXISTS campus_professor_payouts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  campus_id INT NOT NULL,
  professor_name VARCHAR(120) NULL,
  professor_user_id INT NULL,
  amount DECIMAL(12,2) NOT NULL,
  method ENUM('MOBILE_MONEY','BANK','CASH') NOT NULL DEFAULT 'MOBILE_MONEY',
  sent_to VARCHAR(120) NULL,
  reference VARCHAR(120) NULL,
  note VARCHAR(255) NULL,
  paid_by INT NULL,
  paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_professor_payouts_campus (campus_id, paid_at),
  CONSTRAINT fk_professor_payouts_campus FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- People applying to teach: an existing campus, or a new one they propose.
CREATE TABLE IF NOT EXISTS instructor_applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(32) NOT NULL,
  campus_id INT NULL,
  proposed_campus VARCHAR(160) NULL,
  headline VARCHAR(200) NOT NULL,
  experience TEXT NOT NULL,
  results TEXT NULL,
  links VARCHAR(500) NULL,
  sample_url VARCHAR(500) NULL,
  status ENUM('NEW','SHORTLISTED','ACCEPTED','DECLINED') NOT NULL DEFAULT 'NEW',
  admin_note TEXT NULL,
  user_id INT NULL,
  ip VARCHAR(64) NULL,
  reviewed_by INT NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_instructor_applications_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Earnings for campus payments that succeeded before this migration (none expected yet).
INSERT IGNORE INTO campus_professor_earnings (campus_id, payment_id, learner_id, professor_name, plan, gross, share_percent, amount, created_at)
SELECT p.campus_id, p.id, p.user_id, c.professor_name, IF(p.campus_plan = 'YEARLY', 'YEARLY', 'MONTHLY'), p.amount,
       c.professor_share_percent, ROUND(p.amount * c.professor_share_percent / 100, 2), p.created_at
FROM payments p JOIN campuses c ON c.id = p.campus_id
WHERE p.type = 'CAMPUS_MEMBERSHIP' AND p.status = 'SUCCESS';
