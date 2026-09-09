-- Login activity tracking for the admin dashboard: who signed in, when,
-- as what role, from what device, and (resolved later, async) roughly
-- where from. Safe to run once against production.
CREATE TABLE login_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role ENUM('LEARNER','CREATOR','ADMIN') NOT NULL,
  logged_in_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  device_type ENUM('desktop','mobile','tablet') NOT NULL DEFAULT 'desktop',
  browser VARCHAR(40) NULL,
  os VARCHAR(40) NULL,
  country CHAR(2) NULL,
  city VARCHAR(100) NULL,
  ip_address VARCHAR(45) NULL,
  user_id INT NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_login_log_user (user_id),
  INDEX idx_login_log_time (logged_in_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
