ALTER TABLE comments
  ADD COLUMN parent_id INT NULL AFTER course_id,
  ADD CONSTRAINT fk_comments_parent FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE;
