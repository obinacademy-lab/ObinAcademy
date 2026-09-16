-- Two small corrections found while wiring up the school-subscription
-- payment flow (Phase 3):
--
-- 1. payments.school_subscription_id (added in add-school-pricing.sql) can
--    only be set once a school_subscriptions row exists — but on a
--    learner's very FIRST subscribe payment, there is no row yet to point
--    at. school_subscription_creator_id is always set for a
--    SCHOOL_SUBSCRIPTION payment (first payment or renewal alike), so
--    resolve_payment_with_iotec() always knows which creator's school this
--    payment is for, the same way the old (now-historical-only)
--    subscription_tier column let the dead platform-wide system identify a
--    first payment before its subscriptions row existed.
--
-- 2. earnings.course_id was NOT NULL, but a school-subscription earning
--    isn't tied to one specific course — mirrors payments.course_id, which
--    is already nullable for exactly this reason ("NULL for a SUBSCRIPTION
--    payment — it isn't tied to one course").
ALTER TABLE payments
  ADD COLUMN school_subscription_creator_id INT NULL AFTER school_subscription_id,
  ADD FOREIGN KEY (school_subscription_creator_id) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE earnings
  MODIFY COLUMN course_id INT NULL;
