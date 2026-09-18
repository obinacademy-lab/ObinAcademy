-- Lets each creator choose how many days apart their installments fall
-- (e.g. weekly, every 2 weeks, monthly) instead of the previous fixed
-- 14-day gap for everyone. See includes/installments.php.
ALTER TABLE courses
  ADD COLUMN installment_interval_days SMALLINT NOT NULL DEFAULT 14 AFTER installment_count;

ALTER TABLE installment_plans
  ADD COLUMN installment_interval_days SMALLINT NOT NULL DEFAULT 14 AFTER installment_amount;
