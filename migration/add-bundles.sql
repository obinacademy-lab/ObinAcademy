-- Lets a creator package 2+ of their own published courses together for one
-- discounted one-time price. Buying a bundle grants the same kind of
-- PURCHASE-sourced enrollment as buying each course individually would
-- (each course's own access_duration_days still applies) — a bundle is
-- just a bulk checkout, not a new access model. See includes/bundles.php.
CREATE TABLE bundles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  slug VARCHAR(220) NOT NULL,
  description TEXT NULL,
  price DECIMAL(12,2) NOT NULL,
  status ENUM('DRAFT','PUBLISHED') NOT NULL DEFAULT 'DRAFT',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  creator_id INT NOT NULL,
  FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_slug (slug),
  INDEX idx_bundles_creator_status (creator_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE bundle_courses (
  bundle_id INT NOT NULL,
  course_id INT NOT NULL,
  PRIMARY KEY (bundle_id, course_id),
  FOREIGN KEY (bundle_id) REFERENCES bundles(id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE payments
  MODIFY COLUMN type ENUM('COURSE_PURCHASE','PREMIUM_UPGRADE','SUBSCRIPTION','SCHOOL_SUBSCRIPTION','BUNDLE_PURCHASE') NOT NULL DEFAULT 'COURSE_PURCHASE',
  ADD COLUMN bundle_id INT NULL AFTER coupon_id,
  ADD FOREIGN KEY (bundle_id) REFERENCES bundles(id) ON DELETE SET NULL;
