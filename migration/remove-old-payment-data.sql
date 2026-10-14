-- Removes what is left of the old per-course platform from payments and users.
-- Run in phpMyAdmin with the Obin Academy database selected (it should be bold in the left sidebar).
-- Safe to run more than once. Campus payments, memberships and accounts are not touched.

-- 1. What will be removed (read this first).
SELECT type, status, COUNT(*) AS payments, SUM(amount) AS ugx
FROM payments WHERE type <> 'CAMPUS_MEMBERSHIP' GROUP BY type, status;

-- 2. Payments that were not campus payments (old courses, bundles, gifts, subscriptions, instalments).
--    Their reminder log rows go with them (payment_reminders cascades).
DELETE FROM payments WHERE type <> 'CAMPUS_MEMBERSHIP';

-- 3. Any foreign keys still attached to the old payment columns (names differ between servers).
SET @drops := (
  SELECT GROUP_CONCAT(CONCAT('DROP FOREIGN KEY `', CONSTRAINT_NAME, '`') SEPARATOR ', ')
  FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND REFERENCED_TABLE_NAME IS NOT NULL
    AND COLUMN_NAME IN ('course_id', 'subscription_id', 'school_subscription_id', 'school_subscription_creator_id',
                        'affiliate_id', 'coupon_id', 'bundle_id', 'installment_plan_id', 'gift_id')
);
SET @sql := IF(@drops IS NULL, 'SELECT 1', CONCAT('ALTER TABLE payments ', @drops));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 4. The old payment columns, and payments.type narrowed to campus payments only.
ALTER TABLE payments
  DROP COLUMN IF EXISTS course_id,
  DROP COLUMN IF EXISTS subscription_id,
  DROP COLUMN IF EXISTS subscription_tier,
  DROP COLUMN IF EXISTS school_subscription_id,
  DROP COLUMN IF EXISTS school_subscription_creator_id,
  DROP COLUMN IF EXISTS affiliate_id,
  DROP COLUMN IF EXISTS coupon_id,
  DROP COLUMN IF EXISTS bundle_id,
  DROP COLUMN IF EXISTS installment_plan_id,
  DROP COLUMN IF EXISTS gift_id,
  DROP COLUMN IF EXISTS gift_recipient_name,
  DROP COLUMN IF EXISTS gift_recipient_email,
  DROP COLUMN IF EXISTS gift_message,
  DROP COLUMN IF EXISTS gift_subscription_months,
  DROP COLUMN IF EXISTS guest_name,
  DROP COLUMN IF EXISTS guest_email,
  DROP COLUMN IF EXISTS access_token_hash,
  DROP COLUMN IF EXISTS original_amount,
  DROP COLUMN IF EXISTS recovery_email_sent_at,
  MODIFY type ENUM('CAMPUS_MEMBERSHIP') NOT NULL DEFAULT 'CAMPUS_MEMBERSHIP';

-- 5. Creator / school and course-streak fields on accounts, and the retired Creator role.
UPDATE users SET role = 'LEARNER' WHERE role = 'CREATOR';
ALTER TABLE users
  DROP COLUMN IF EXISTS school_name,
  DROP COLUMN IF EXISTS school_cover_url,
  DROP COLUMN IF EXISTS pricing_model,
  DROP COLUMN IF EXISTS school_monthly_price,
  DROP COLUMN IF EXISTS new_course_emails_opt_out,
  DROP COLUMN IF EXISTS contact_visible_to_creators,
  DROP COLUMN IF EXISTS xp_points,
  DROP COLUMN IF EXISTS current_streak,
  DROP COLUMN IF EXISTS longest_streak,
  DROP COLUMN IF EXISTS last_active_date,
  MODIFY role ENUM('LEARNER', 'ADMIN') NOT NULL DEFAULT 'LEARNER';

-- 6. Check: should list only CAMPUS_MEMBERSHIP (or nothing yet).
SELECT type, COUNT(*) AS payments FROM payments GROUP BY type;
