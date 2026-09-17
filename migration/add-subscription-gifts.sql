-- Extends course_gifts (see migration/add-course-gifts.sql) to also cover a
-- course whose creator's school is on MONTHLY_SUBSCRIPTION pricing — a gift
-- like that buys the recipient a fixed number of months of access to one
-- course from that school, rather than a one-time lifetime/duration
-- purchase. See includes/gifts.php.
ALTER TABLE course_gifts
  ADD COLUMN kind ENUM('COURSE','SUBSCRIPTION') NOT NULL DEFAULT 'COURSE' AFTER course_id,
  ADD COLUMN subscription_months TINYINT NULL AFTER kind,
  -- The phone the buyer paid with — school_subscriptions.phone is NOT NULL,
  -- so claiming a SUBSCRIPTION-kind gift needs something to seed that
  -- column with; it's overwritten the moment the recipient renews with
  -- their own number. NULL for a COURSE-kind gift, which needs no phone.
  ADD COLUMN phone VARCHAR(32) NULL AFTER subscription_months;

ALTER TABLE payments
  ADD COLUMN gift_subscription_months TINYINT NULL AFTER gift_message;
