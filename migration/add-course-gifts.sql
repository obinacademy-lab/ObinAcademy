-- Lets a logged-in buyer pay for one course as a gift for someone else by
-- email — the buyer's own account never gets access; the recipient claims
-- it via an emailed link, creating or logging into their own account first
-- if they don't have one yet. See includes/gifts.php.
CREATE TABLE course_gifts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  status ENUM('PENDING','CLAIMED') NOT NULL DEFAULT 'PENDING',
  recipient_name VARCHAR(191) NOT NULL,
  recipient_email VARCHAR(191) NOT NULL,
  message TEXT NULL,
  claim_token_hash VARCHAR(64) NOT NULL,
  claimed_by_user_id INT NULL,
  claimed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  buyer_id INT NOT NULL,
  course_id INT NOT NULL,
  FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  FOREIGN KEY (claimed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  UNIQUE KEY uniq_claim_token_hash (claim_token_hash),
  INDEX idx_course_gifts_buyer (buyer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE payments
  MODIFY COLUMN type ENUM('COURSE_PURCHASE','PREMIUM_UPGRADE','SUBSCRIPTION','SCHOOL_SUBSCRIPTION','BUNDLE_PURCHASE','INSTALLMENT_PAYMENT','COURSE_GIFT') NOT NULL DEFAULT 'COURSE_PURCHASE',
  ADD COLUMN gift_id INT NULL AFTER installment_plan_id,
  -- Carried on the payment itself while PENDING, same convention as
  -- guest_name/guest_email — course_gifts doesn't exist yet until the
  -- payment succeeds (apply_course_gift_payment_success() creates it).
  ADD COLUMN gift_recipient_name VARCHAR(191) NULL AFTER gift_id,
  ADD COLUMN gift_recipient_email VARCHAR(191) NULL AFTER gift_recipient_name,
  ADD COLUMN gift_message TEXT NULL AFTER gift_recipient_email,
  ADD FOREIGN KEY (gift_id) REFERENCES course_gifts(id) ON DELETE SET NULL;
