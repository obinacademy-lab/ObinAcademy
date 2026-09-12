CREATE TABLE comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  body TEXT NOT NULL,
  status ENUM('VISIBLE','HIDDEN') NOT NULL DEFAULT 'VISIBLE',
  hidden_reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  user_id INT NOT NULL,
  course_id INT NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  INDEX idx_comments_course_status (course_id, status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
