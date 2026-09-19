-- Lets the platform send one automatic follow-up email to a learner who
-- marked "Keep me updated" on a course (course_interest) but never
-- enrolled — separate from the creator's own manual outreach to the same
-- list. reminder_sent_at is the send guard (NULL = not sent yet); opt-out
-- is its own column since a learner may want new-course/retention emails
-- but not these, or vice versa. See includes/interest.php.
ALTER TABLE course_interest
  ADD COLUMN reminder_sent_at DATETIME NULL AFTER created_at;

ALTER TABLE users
  ADD COLUMN interest_emails_opt_out TINYINT(1) NOT NULL DEFAULT 0 AFTER new_course_emails_opt_out;
