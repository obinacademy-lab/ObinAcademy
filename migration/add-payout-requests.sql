-- Professors can ask to be paid. A request is for everything owed on the campus at that moment (a payout
-- always settles every unpaid share), with the mobile money number to send it to. The admin pays it on
-- Admin, Professor Pay (recording the payout marks the request paid) or declines it with a reason.
-- Run in phpMyAdmin with the Obin Academy database selected. Safe to run more than once.

CREATE TABLE IF NOT EXISTS campus_payout_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  campus_id INT NOT NULL,
  professor_user_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  phone VARCHAR(32) NOT NULL,
  payout_name VARCHAR(120) NULL,
  note VARCHAR(255) NULL,
  status ENUM('PENDING','PAID','DECLINED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  decline_reason VARCHAR(255) NULL,
  payout_id INT NULL,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  decided_at DATETIME NULL,
  decided_by INT NULL,
  KEY idx_payout_requests_status (status, requested_at),
  KEY idx_payout_requests_campus (campus_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admin notifications carry new kinds (payment requests, teach applications): free text instead of a fixed list.
ALTER TABLE admin_notifications MODIFY type VARCHAR(40) NOT NULL;
