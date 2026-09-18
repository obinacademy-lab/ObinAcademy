-- Lets the creator set the exact amount for a course's first installment
-- themselves, instead of the platform auto-splitting price/count. Always
-- exactly 2 installments now — the second is whatever's left of the price,
-- computed live (see includes/installments.php's installment_amount_due()).
-- installment_count stays on both tables (always written as 2 going
-- forward) since existing display code keys off it.
ALTER TABLE courses
  ADD COLUMN first_installment_amount DECIMAL(12,2) NULL AFTER installment_interval_days;
