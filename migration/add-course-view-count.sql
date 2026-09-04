-- Adds a public view counter to courses. Run once in phpMyAdmin, then
-- deploy the code that reads/writes it (existing rows start at 0 and
-- accumulate from the first page load after this runs).

ALTER TABLE courses
  ADD COLUMN view_count INT NOT NULL DEFAULT 0 AFTER premium_price;
