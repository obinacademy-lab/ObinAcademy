-- Who viewed each course (creator "Course Viewers" page).
--
-- course_views: one row per (course, member) or per (course, anonymous visitor).
--   * Members (logged in): user_id set, visitor_id NULL.
--   * Guests (no account, accepted cookies): visitor_id = the oa_visitor cookie, user_id NULL.
--     A guest row is removed when that same visitor later views as a logged-in member.
--   Guests never carry contact details; creators see them only as "Guest viewer".
--
-- users.contact_visible_to_creators: a member can switch this off in Settings to stop
-- creators seeing their name and contact details. Default 1 (visible).
--
-- Both statements are additive and safe to run once. (If the ALTER says the column
-- already exists, it has been applied and can be ignored.)

CREATE TABLE IF NOT EXISTS course_views (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  user_id INT NULL,
  visitor_id VARCHAR(32) NULL,
  view_count INT NOT NULL DEFAULT 1,
  first_viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_member_view (course_id, user_id),
  UNIQUE KEY uniq_guest_view (course_id, visitor_id),
  INDEX idx_course_views_recent (course_id, last_viewed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE users
  ADD COLUMN contact_visible_to_creators TINYINT(1) NOT NULL DEFAULT 1;
