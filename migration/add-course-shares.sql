-- Course-share tracking for the admin dashboard: which channel a course
-- link was shared through, and how many distinct people came back through
-- that exact link (a proxy for "did this get forwarded beyond the first
-- recipient"). Safe to run once against production.
CREATE TABLE course_shares (
  id INT AUTO_INCREMENT PRIMARY KEY,
  channel VARCHAR(20) NOT NULL,
  share_token VARCHAR(16) NOT NULL UNIQUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  course_id INT NOT NULL,
  sharer_id INT NULL,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  FOREIGN KEY (sharer_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_course_shares_course (course_id),
  INDEX idx_course_shares_token (share_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE course_share_visits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  visitor_id VARCHAR(32) NULL,
  visited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  share_id INT NOT NULL,
  FOREIGN KEY (share_id) REFERENCES course_shares(id) ON DELETE CASCADE,
  INDEX idx_csv_share (share_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
