ALTER TABLE comments
  ADD COLUMN reply_to_comment_id INT NULL AFTER parent_id,
  ADD CONSTRAINT fk_comments_reply_to FOREIGN KEY (reply_to_comment_id) REFERENCES comments(id) ON DELETE SET NULL;
