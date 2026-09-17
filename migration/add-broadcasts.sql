-- Logs a creator's WhatsApp broadcasts to their followers. There's no
-- WhatsApp Business API connected yet, so this doesn't actually send
-- anything itself — includes/broadcasts.php generates a wa.me deep link per
-- follower (prefilled with the message) that the creator clicks through
-- one at a time in their own WhatsApp. This table is just the history/log
-- of what was composed and how many followers it was prepared for; once a
-- real Business API account exists, that's a new sending path that reuses
-- this same log rather than a schema change.
CREATE TABLE broadcasts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  message TEXT NOT NULL,
  recipient_count INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  creator_id INT NOT NULL,
  FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_broadcasts_creator (creator_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
