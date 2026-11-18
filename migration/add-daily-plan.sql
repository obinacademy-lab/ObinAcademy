-- Day pass: a third campus plan that opens a campus for 24 hours.
-- Run once in phpMyAdmin. The site keeps the day pass hidden until this has run
-- (it checks for campuses.daily_price, which is added last on purpose).
-- 'DAILY' is appended after the existing values so stored MONTHLY/YEARLY rows are untouched.

ALTER TABLE payments
  MODIFY COLUMN campus_plan ENUM('MONTHLY','YEARLY','DAILY') NULL;

ALTER TABLE campus_memberships
  MODIFY COLUMN plan ENUM('MONTHLY','YEARLY','DAILY') NOT NULL;

ALTER TABLE campus_professor_earnings
  MODIFY COLUMN plan ENUM('MONTHLY','YEARLY','DAILY') NOT NULL DEFAULT 'MONTHLY';

ALTER TABLE campuses
  ADD COLUMN daily_price DECIMAL(12,2) NOT NULL DEFAULT 10000 AFTER yearly_price;
