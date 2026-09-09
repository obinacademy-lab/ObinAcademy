ALTER TABLE enrollments
  ADD COLUMN last_activity_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER progress,
  ADD INDEX idx_enrollments_activity (user_id, last_activity_at);

CREATE TABLE retention_notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  stage VARCHAR(10) NOT NULL,
  template_key VARCHAR(30) NOT NULL,
  sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  user_id INT NOT NULL,
  enrollment_id INT NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE,
  INDEX idx_retention_enrollment_stage (enrollment_id, stage),
  INDEX idx_retention_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
