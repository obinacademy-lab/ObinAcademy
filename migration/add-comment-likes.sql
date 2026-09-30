-- Lets any logged-in user like a comment or reply on a course's discussion
-- (public/courses/view.php) — a lightweight engagement signal, independent
-- of enrollment/reviews. See includes/comments.php.
CREATE TABLE comment_likes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  comment_id INT NOT NULL,
  user_id INT NOT NULL,
  FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_comment_user (comment_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
