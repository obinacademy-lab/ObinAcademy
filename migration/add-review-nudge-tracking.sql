-- Send guard for the "how was it?" review-request email, fired once an
-- enrollment reaches 100% completion (see includes/review_nudges.php).
-- NULL means not sent yet.
ALTER TABLE enrollments
  ADD COLUMN review_nudge_sent_at DATETIME NULL AFTER last_activity_at;
