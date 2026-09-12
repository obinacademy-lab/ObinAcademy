ALTER TABLE payments
  ADD COLUMN quantity INT NOT NULL DEFAULT 1 AFTER ticket_tier,
  ADD COLUMN extra_attendees TEXT NULL AFTER quantity;
