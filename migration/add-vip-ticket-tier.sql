ALTER TABLE courses
  ADD COLUMN vip_price DECIMAL(12,2) NULL AFTER ticket_capacity,
  ADD COLUMN vip_capacity INT NULL AFTER vip_price;

ALTER TABLE enrollments
  ADD COLUMN ticket_tier ENUM('ORDINARY','VIP') NOT NULL DEFAULT 'ORDINARY' AFTER course_id;

ALTER TABLE payments
  ADD COLUMN ticket_tier ENUM('ORDINARY','VIP') NULL AFTER course_id;
