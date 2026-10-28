-- Student referrals: every student gets a referral link (users.ref_code). When someone creates a brand new
-- account through it, that account is permanently linked to the referrer (users.referred_by_user_id) — set
-- once, at signup, never changed afterwards. From then on, every successful campus payment that referred
-- student ever makes, on any campus, earns the referrer a 10% commission, for as long as the student keeps
-- paying (AFFILIATE_COMMISSION_PERCENT in includes/affiliates.php). Payouts work exactly like campus
-- professor pay: earnings build up, the affiliate requests everything owed, the admin sends it to their
-- mobile money and records it, which settles the request.
-- Run in phpMyAdmin with the Obin Academy database selected. Run it once (the CREATE TABLEs below tolerate
-- a second run; the ALTER TABLE on users does not — it fails harmlessly with "duplicate column" if rerun).
-- The site keeps working before it is run (affiliates_ready() is false until the users columns exist).

ALTER TABLE users
  ADD COLUMN ref_code VARCHAR(12) NULL,
  ADD COLUMN referred_by_user_id INT NULL,
  ADD UNIQUE KEY uniq_users_ref_code (ref_code);

CREATE TABLE IF NOT EXISTS campus_affiliate_earnings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  affiliate_user_id INT NOT NULL,
  referred_user_id INT NOT NULL,
  payment_id INT NOT NULL,
  campus_id INT NULL,
  campus_name VARCHAR(120) NULL,
  plan ENUM('MONTHLY','YEARLY') NOT NULL DEFAULT 'MONTHLY',
  gross DECIMAL(12,2) NOT NULL,
  share_percent DECIMAL(5,2) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  payout_id INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_affiliate_earnings_payment (payment_id),
  KEY idx_affiliate_earnings_affiliate (affiliate_user_id, payout_id),
  KEY idx_affiliate_earnings_referred (referred_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS campus_affiliate_payouts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  affiliate_user_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  method ENUM('MOBILE_MONEY','BANK','CASH') NOT NULL DEFAULT 'MOBILE_MONEY',
  sent_to VARCHAR(120) NULL,
  reference VARCHAR(120) NULL,
  note VARCHAR(255) NULL,
  paid_by INT NULL,
  paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_affiliate_payouts_affiliate (affiliate_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS campus_affiliate_payout_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  affiliate_user_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  phone VARCHAR(32) NOT NULL,
  payout_name VARCHAR(120) NULL,
  note VARCHAR(255) NULL,
  status ENUM('PENDING','PAID','DECLINED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  decline_reason VARCHAR(255) NULL,
  payout_id INT NULL,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  decided_at DATETIME NULL,
  decided_by INT NULL,
  KEY idx_affiliate_payout_requests_status (status, requested_at),
  KEY idx_affiliate_payout_requests_affiliate (affiliate_user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
