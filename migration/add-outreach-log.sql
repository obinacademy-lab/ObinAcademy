-- Outreach log: who an admin messaged (lead follow-ups, failed-payment reminders), on which channel, and when.
-- Additive only. The admin pages work without these tables (they just don't show history until this is run).

CREATE TABLE IF NOT EXISTS lead_contacts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  lead_id INT NOT NULL,
  admin_id INT NULL,
  channel ENUM('whatsapp','email') NOT NULL,
  template VARCHAR(40) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_lead_contacts_lead (lead_id),
  CONSTRAINT fk_lead_contacts_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payment_reminders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  payment_id INT NOT NULL,
  admin_id INT NULL,
  channel ENUM('whatsapp','email') NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_payment_reminders_payment (payment_id),
  CONSTRAINT fk_payment_reminders_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
