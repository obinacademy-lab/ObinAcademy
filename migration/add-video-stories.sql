-- Video stories: real students on camera, uploaded by the admin. Replaces the old text testimonials,
-- which were all written about the previous per-course platform and are removed here for good.
-- Run in phpMyAdmin with the Obin Academy database selected. Safe to run more than once.

CREATE TABLE IF NOT EXISTS video_stories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_name VARCHAR(120) NOT NULL,
  campus_id INT NULL,
  quote VARCHAR(280) NOT NULL,
  video_url VARCHAR(500) NOT NULL,
  duration_seconds INT NULL,
  status ENUM('DRAFT', 'PUBLISHED') NOT NULL DEFAULT 'DRAFT',
  sort_order INT NOT NULL DEFAULT 0,
  consent_confirmed TINYINT(1) NOT NULL DEFAULT 0,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  published_at DATETIME NULL,
  KEY idx_video_stories_status (status, sort_order),
  KEY idx_video_stories_campus (campus_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The old text stories (every one is about the old courses) and their table.
DROP TABLE IF EXISTS testimonials;
