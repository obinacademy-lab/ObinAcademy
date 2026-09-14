-- Opt-in interest list: a logged-in learner explicitly clicks "Keep me
-- updated" on a course they haven't bought yet. Only then does the course's
-- creator get to see that learner's contact details for that course — never
-- as a byproduct of merely viewing the page.
CREATE TABLE course_interest (
  id INT AUTO_INCREMENT PRIMARY KEY,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  user_id INT NOT NULL,
  course_id INT NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_user_course (user_id, course_id),
  INDEX idx_course_interest_course (course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
