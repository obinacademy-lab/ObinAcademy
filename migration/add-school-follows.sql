-- Lets a learner follow a creator's school without buying anything yet —
-- the soft-commitment audience a creator can reach later (WhatsApp
-- broadcast, "new course" notifications), separate from course_interest
-- which is scoped to one specific course. See includes/follows.php.
CREATE TABLE school_follows (
  id INT AUTO_INCREMENT PRIMARY KEY,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  learner_id INT NOT NULL,
  creator_id INT NOT NULL,
  FOREIGN KEY (learner_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_follow (learner_id, creator_id),
  INDEX idx_follows_creator (creator_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
