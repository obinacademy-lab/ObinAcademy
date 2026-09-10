ALTER TABLE users
  ADD COLUMN new_course_emails_opt_out TINYINT(1) NOT NULL DEFAULT 0;
