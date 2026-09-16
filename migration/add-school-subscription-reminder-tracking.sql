-- Tracks whether a renewal reminder has already gone out for the CURRENT
-- period, so cron/track-maintenance.php's reminder sweep doesn't re-email
-- the same learner every run until they actually renew (which resets this
-- back to NULL — see apply_school_subscription_payment_success()).
ALTER TABLE school_subscriptions
  ADD COLUMN reminder_sent_at DATETIME NULL AFTER last_charge_attempt_at;
