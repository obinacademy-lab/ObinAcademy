ALTER TABLE withdrawal_requests
  MODIFY COLUMN creator_id INT NULL,
  ADD COLUMN payee_type ENUM('CREATOR','AFFILIATE') NOT NULL DEFAULT 'CREATOR' AFTER phone,
  ADD COLUMN affiliate_id INT NULL AFTER creator_id,
  ADD FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE;
