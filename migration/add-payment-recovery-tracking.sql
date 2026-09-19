-- Send guard for the abandoned-checkout recovery email (see
-- includes/payment_recovery.php) — NULL means not sent yet. Scoped to
-- COURSE_PURCHASE/BUNDLE_PURCHASE payments that end up FAILED.
ALTER TABLE payments
  ADD COLUMN recovery_email_sent_at DATETIME NULL AFTER status_message;
