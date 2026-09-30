-- In-app notifications for a logged-in user's own activity — currently just
-- course comment replies, likes, and new top-level comments on a creator's
-- own course. See includes/user_notifications.php.
--
-- DROP IF EXISTS first: a `user_notifications` table already existed under
-- the old Community module (different columns — user_id/related_id, no
-- actor_user_id) that migration/drop-community-module.sql was meant to
-- remove. If that cleanup was ever skipped on this database, plain CREATE
-- TABLE here would fail with "table already exists" — safe to drop first
-- since nothing in the current codebase reads that old table.
DROP TABLE IF EXISTS user_notifications;
CREATE TABLE user_notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type VARCHAR(30) NOT NULL,
  message VARCHAR(500) NOT NULL,
  link_url VARCHAR(500) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  recipient_user_id INT NOT NULL,
  actor_user_id INT NULL,
  FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_user_notif_recipient (recipient_user_id, is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
