-- Coupon codes a creator can run for their own courses — a percentage or
-- fixed-amount discount, optionally scoped to one specific course (course_id
-- NULL = valid on any of that creator's courses), with an optional expiry
-- and usage cap. See includes/coupons.php.
CREATE TABLE coupons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL,
  discount_type ENUM('PERCENT','FIXED') NOT NULL,
  discount_value DECIMAL(12,2) NOT NULL,
  max_uses INT NULL,
  uses_count INT NOT NULL DEFAULT 0,
  expires_at DATETIME NULL,
  status ENUM('ACTIVE','DISABLED') NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  creator_id INT NOT NULL,
  course_id INT NULL,
  FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_creator_code (creator_id, code),
  INDEX idx_coupons_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One redemption per learner per coupon (uniq_user_coupon) — a learner
-- can't stack the same code twice. user_id is NULL for a guest checkout
-- (mirrors payments.user_id) — MySQL treats NULL as distinct in a unique
-- index, so guest redemptions aren't deduped against each other, only
-- max_uses caps them; a real dedup guarantee needs an account. Only
-- written on a SUCCESS payment (not PENDING/FAILED attempts), so
-- uses_count stays an accurate count of real redemptions.
CREATE TABLE coupon_redemptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  coupon_id INT NOT NULL,
  user_id INT NULL,
  payment_id INT NULL,
  FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL,
  UNIQUE KEY uniq_user_coupon (user_id, coupon_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE payments
  ADD COLUMN coupon_id INT NULL AFTER affiliate_id,
  ADD FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE SET NULL;
