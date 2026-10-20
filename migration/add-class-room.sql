-- The campus community becomes a WhatsApp-style class room: replies, reactions, stickers, photos and
-- wins with their own comment threads; unread tracking for alerts; and a per-person switch for the
-- notification sound. Run in phpMyAdmin with the Obin Academy database selected. Run it once.
-- The site keeps working before it is run (the class room shows plain messages until then).

ALTER TABLE campus_posts
  ADD COLUMN kind ENUM('TEXT','STICKER','IMAGE','WIN') NOT NULL DEFAULT 'TEXT' AFTER user_id,
  ADD COLUMN reply_to INT NULL AFTER kind,
  ADD COLUMN parent_id INT NULL AFTER reply_to,
  ADD COLUMN sticker VARCHAR(40) NULL AFTER body,
  ADD COLUMN image_url VARCHAR(500) NULL AFTER sticker,
  ADD COLUMN title VARCHAR(120) NULL AFTER image_url,
  ADD KEY idx_campus_posts_thread (campus_id, parent_id, id);

-- One reaction per person per message, as in WhatsApp (picking another emoji replaces it).
CREATE TABLE IF NOT EXISTS campus_post_reactions (
  post_id INT NOT NULL,
  user_id INT NOT NULL,
  emoji VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL, -- _bin: every emoji counts as its own value
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (post_id, user_id),
  KEY idx_post_reactions_post (post_id),
  CONSTRAINT fk_post_reactions_post FOREIGN KEY (post_id) REFERENCES campus_posts (id) ON DELETE CASCADE,
  CONSTRAINT fk_post_reactions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The last message each person has seen in each class room (for unread counts and alerts).
CREATE TABLE IF NOT EXISTS campus_chat_reads (
  campus_id INT NOT NULL,
  user_id INT NOT NULL,
  last_read_id INT NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (campus_id, user_id),
  CONSTRAINT fk_chat_reads_campus FOREIGN KEY (campus_id) REFERENCES campuses (id) ON DELETE CASCADE,
  CONSTRAINT fk_chat_reads_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Notification sound for new class room messages: on unless the person turns it off in Settings.
ALTER TABLE users ADD COLUMN chat_sound TINYINT(1) NOT NULL DEFAULT 1;
