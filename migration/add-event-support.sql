-- Additive only — no data migration, no touch to enrollments/payments/
-- earnings/withdrawal_requests. Every existing row keeps working exactly
-- as before: type defaults to 'COURSE' and every new event_* column is
-- NULL for it.
ALTER TABLE courses
  ADD COLUMN type ENUM('COURSE','EVENT') NOT NULL DEFAULT 'COURSE' AFTER slug,
  ADD COLUMN event_starts_at DATETIME NULL,
  ADD COLUMN event_ends_at DATETIME NULL,
  ADD COLUMN event_location VARCHAR(255) NULL,
  ADD COLUMN event_online_url VARCHAR(500) NULL,
  ADD COLUMN ticket_capacity INT NULL;

ALTER TABLE courses
  ADD INDEX idx_courses_type_starts (type, event_starts_at);
