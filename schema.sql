-- Obin Academy — MySQL schema
-- Import this once in hPanel's phpMyAdmin (or via `mysql -u user -p dbname < schema.sql`)
-- after creating an empty database. Uses utf8mb4 throughout for full emoji/unicode support.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(191) NOT NULL,
  email VARCHAR(191) NOT NULL UNIQUE,
  phone VARCHAR(32) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('LEARNER','CREATOR','ADMIN') NOT NULL DEFAULT 'LEARNER',
  headline VARCHAR(191) NULL,
  bio TEXT NULL,
  avatar_url VARCHAR(500) NULL,
  facebook_url VARCHAR(500) NULL,
  instagram_url VARCHAR(500) NULL,
  youtube_url VARCHAR(500) NULL,
  tiktok_url VARCHAR(500) NULL,
  linkedin_url VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
CREATE TABLE creator_applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
  expertise TEXT NOT NULL,
  motivation TEXT NOT NULL,
  rejection_reason TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  slug VARCHAR(120) NOT NULL UNIQUE,
  icon VARCHAR(60) NOT NULL DEFAULT 'sparkles'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
CREATE TABLE courses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(191) NOT NULL,
  slug VARCHAR(191) NOT NULL UNIQUE,
  summary VARCHAR(500) NOT NULL,
  description TEXT NOT NULL,
  thumbnail_url VARCHAR(500) NULL,
  price DECIMAL(12,2) NOT NULL DEFAULT 0,
  sale_price DECIMAL(12,2) NULL,
  access_duration_days INT NULL,
  premium_price DECIMAL(12,2) NULL,
  status ENUM('DRAFT','PENDING_REVIEW','PUBLISHED','REJECTED','REMOVED') NOT NULL DEFAULT 'DRAFT',
  rejection_reason TEXT NULL,
  submitted_at DATETIME NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  creator_id INT NOT NULL,
  category_id INT NOT NULL,
  FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id),
  INDEX idx_courses_status (status),
  INDEX idx_courses_creator (creator_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
CREATE TABLE modules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(191) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  course_id INT NOT NULL,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
CREATE TABLE lessons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(191) NOT NULL,
  type ENUM('VIDEO','PDF') NOT NULL,
  file_url VARCHAR(500) NOT NULL,
  file_name VARCHAR(255) NULL,
  duration INT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  module_id INT NOT NULL,
  FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- user_id is nullable to support guest checkout: a guest buyer has no
-- account, so the sale is tracked by guest_name/guest_email instead, and
-- access_token_hash (sha256 of a token emailed to them) is their only way
-- back in — never store the plaintext token, same pattern as
-- password_reset_tokens.
CREATE TABLE enrollments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  progress DECIMAL(5,2) NOT NULL DEFAULT 0,
  enrolled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NULL,
  is_premium TINYINT(1) NOT NULL DEFAULT 0,
  user_id INT NULL,
  guest_name VARCHAR(191) NULL,
  guest_email VARCHAR(191) NULL,
  access_token_hash VARCHAR(64) NULL,
  course_id INT NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_user_course (user_id, course_id),
  UNIQUE KEY uniq_access_token_hash (access_token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
CREATE TABLE payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  iotec_transaction_id VARCHAR(191) NULL UNIQUE,
  amount DECIMAL(12,2) NOT NULL,
  original_amount DECIMAL(12,2) NULL,
  phone VARCHAR(32) NOT NULL,
  type ENUM('COURSE_PURCHASE','PREMIUM_UPGRADE') NOT NULL DEFAULT 'COURSE_PURCHASE',
  status ENUM('PENDING','SUCCESS','FAILED') NOT NULL DEFAULT 'PENDING',
  status_message VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  user_id INT NULL,
  guest_name VARCHAR(191) NULL,
  guest_email VARCHAR(191) NULL,
  access_token_hash VARCHAR(64) NULL,
  course_id INT NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  INDEX idx_payments_user_course_status (user_id, course_id, status),
  UNIQUE KEY uniq_access_token_hash (access_token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
CREATE TABLE earnings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  amount DECIMAL(12,2) NOT NULL,
  gross_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  platform_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  creator_id INT NOT NULL,
  course_id INT NOT NULL,
  FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
CREATE TABLE withdrawal_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  amount DECIMAL(12,2) NOT NULL,
  phone VARCHAR(32) NOT NULL DEFAULT '',
  status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
  note VARCHAR(500) NULL,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  creator_id INT NOT NULL,
  FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Issued automatically once an enrollment's progress hits 100% (see
-- update_lesson_progress() in includes/enrollment.php). code is the public,
-- unguessable verification handle — certificate.php?code=... is viewable by
-- anyone holding the link, same trust model as a real paper certificate.
CREATE TABLE certificates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  enrollment_id INT NOT NULL UNIQUE,
  course_id INT NOT NULL,
  issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
CREATE TABLE audit_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  action VARCHAR(120) NOT NULL,
  target_type VARCHAR(60) NOT NULL,
  target_label VARCHAR(255) NOT NULL,
  detail VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actor_id INT NULL,
  actor_name VARCHAR(191) NOT NULL,
  FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
CREATE TABLE testimonials (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quote TEXT NOT NULL,
  rating INT NOT NULL DEFAULT 5,
  status ENUM('PENDING_REVIEW','PUBLISHED','REJECTED') NOT NULL DEFAULT 'PENDING_REVIEW',
  rejection_reason TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME NULL,
  author_id INT NOT NULL,
  FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
CREATE TABLE reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  rating INT NOT NULL,
  comment TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  course_id INT NOT NULL,
  author_id INT NOT NULL,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_course_author (course_id, author_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
CREATE TABLE password_reset_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  token_hash VARCHAR(255) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  user_id INT NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Visitor Intelligence — one row per browsing session (device/geo/referrer,
-- plus running counters kept as a cache so the analytics dashboard doesn't
-- have to GROUP BY the (much larger) pageviews table for every summary
-- query). session_token is a separate, short sliding-window cookie from
-- visitor_id (the long-lived identity cookie) so a session naturally expires
-- after ~30 minutes of inactivity without needing a cron sweep to close it.
CREATE TABLE visitor_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  visitor_id VARCHAR(32) NOT NULL,
  session_token VARCHAR(32) NOT NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  entry_path VARCHAR(500) NOT NULL,
  exit_path VARCHAR(500) NOT NULL,
  referrer_source ENUM('google','social','direct','other') NOT NULL DEFAULT 'direct',
  device_type ENUM('desktop','mobile','tablet') NOT NULL DEFAULT 'desktop',
  browser VARCHAR(40) NULL,
  os VARCHAR(40) NULL,
  country CHAR(2) NULL,
  city VARCHAR(100) NULL,
  -- Transient only — set at pageview time so the cron geo sweep has something
  -- to look up later, and cleared (set NULL) by that same sweep the moment
  -- it resolves country/city (or gives up). Never queried, never displayed,
  -- never kept once geo resolution is done.
  ip_address VARCHAR(45) NULL,
  pageview_count INT NOT NULL DEFAULT 0,
  max_scroll_depth INT NOT NULL DEFAULT 0,
  is_new_visitor TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uniq_session_token (session_token),
  INDEX idx_visitor_id (visitor_id),
  INDEX idx_started_at (started_at),
  INDEX idx_geo_pending (country, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per pageview. visitor_id is denormalized here (not just reachable
-- via session_id) so "pages/courses this lead viewed" is a single-hop query
-- against a lead's visitor_id, not a join through visitor_sessions.
CREATE TABLE visitor_pageviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  session_id INT NOT NULL,
  visitor_id VARCHAR(32) NOT NULL,
  path VARCHAR(500) NOT NULL,
  entered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  time_on_page_seconds INT NULL,
  scroll_depth_pct INT NOT NULL DEFAULT 0,
  FOREIGN KEY (session_id) REFERENCES visitor_sessions(id) ON DELETE CASCADE,
  INDEX idx_session_id (session_id),
  INDEX idx_visitor_id (visitor_id),
  INDEX idx_path (path(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- A voluntary lead capture — only ever created from a submitted form, never
-- from tracking data alone. visitor_id (nullable — a lead could in principle
-- be added manually) links back to visitor_sessions/visitor_pageviews so the
-- CRM can show a lead's real browsing history on demand.
CREATE TABLE leads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(191) NOT NULL,
  email VARCHAR(191) NOT NULL,
  phone VARCHAR(32) NULL,
  lead_type ENUM('learner','creator') NOT NULL DEFAULT 'learner',
  source ENUM('google','social','direct','other') NOT NULL DEFAULT 'direct',
  status ENUM('NEW','CONTACTED','INTERESTED','ENROLLED','CREATOR','LOST') NOT NULL DEFAULT 'NEW',
  visitor_id VARCHAR(32) NULL,
  first_visit_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_visit_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  visit_count INT NOT NULL DEFAULT 1,
  consent_marketing TINYINT(1) NOT NULL DEFAULT 0,
  unsubscribed TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_email (email),
  INDEX idx_status (status),
  INDEX idx_visitor_id (visitor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE lead_notes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  lead_id INT NOT NULL,
  admin_id INT NOT NULL,
  note TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
  FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tracks which drip-sequence steps (day 3/5/7 — day 1 is the immediate
-- welcome email, not logged here) have been sent, so the cron sweep can use
-- an idempotent "insert once" guard instead of trusting its own timing.
CREATE TABLE lead_sequence_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  lead_id INT NOT NULL,
  step TINYINT NOT NULL,
  sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_lead_step (lead_id, step)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE admin_notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type ENUM('new_lead','creator_request','pricing_revisit','stale_returning_visitor') NOT NULL,
  message VARCHAR(500) NOT NULL,
  related_lead_id INT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (related_lead_id) REFERENCES leads(id) ON DELETE CASCADE,
  INDEX idx_is_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Community module. `communities` models both course communities (auto-
-- created when a course is first published) and creator communities (auto-
-- created when a creator application is approved) in one table via two
-- nullable, individually-UNIQUE parent columns — the standard polymorphic-
-- parent pattern, so every child table below references "a community"
-- generically without caring which kind it is.
CREATE TABLE communities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type ENUM('course','creator') NOT NULL,
  course_id INT NULL,
  creator_id INT NULL,
  name VARCHAR(191) NOT NULL,
  slug VARCHAR(191) NOT NULL UNIQUE,
  description TEXT NULL,
  banner_url VARCHAR(500) NULL,
  member_count INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_course (course_id),
  UNIQUE KEY uniq_creator (creator_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE community_members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  community_id INT NOT NULL,
  user_id INT NOT NULL,
  role ENUM('member','moderator','owner') NOT NULL DEFAULT 'member',
  joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_member (community_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE community_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  community_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  icon VARCHAR(10) NOT NULL DEFAULT '💬',
  slug VARCHAR(100) NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_category_slug (community_id, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE community_posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  community_id INT NOT NULL,
  category_id INT NULL,
  author_id INT NOT NULL,
  type ENUM('post','question','success_story','poll') NOT NULL DEFAULT 'post',
  body TEXT NOT NULL,
  image_url VARCHAR(500) NULL,
  file_url VARCHAR(500) NULL,
  file_name VARCHAR(255) NULL,
  link_url VARCHAR(500) NULL,
  -- Comma-delimited, leading+trailing comma (",tag1,tag2,"), searched via
  -- LIKE '%,tag%,' — plain VARCHAR rather than JSON since Hostinger's MySQL
  -- version isn't guaranteed to have JSON functions/indexing, and LIKE scans
  -- a JSON column exactly as unindexed as a CSV one anyway.
  hashtags VARCHAR(255) NULL,
  is_pinned TINYINT(1) NOT NULL DEFAULT 0,
  like_count INT NOT NULL DEFAULT 0,
  comment_count INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES community_categories(id) ON DELETE SET NULL,
  FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_community_created (community_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE community_post_likes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  post_id INT NOT NULL,
  user_id INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_post_like (post_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE community_comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  post_id INT NOT NULL,
  parent_comment_id INT NULL,
  author_id INT NOT NULL,
  body TEXT NOT NULL,
  like_count INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
  FOREIGN KEY (parent_comment_id) REFERENCES community_comments(id) ON DELETE CASCADE,
  FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_post_id (post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE community_comment_likes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  comment_id INT NOT NULL,
  user_id INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (comment_id) REFERENCES community_comments(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_comment_like (comment_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE community_polls (
  id INT AUTO_INCREMENT PRIMARY KEY,
  post_id INT NOT NULL UNIQUE,
  closes_at DATETIME NULL,
  FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE community_poll_options (
  id INT AUTO_INCREMENT PRIMARY KEY,
  poll_id INT NOT NULL,
  label VARCHAR(191) NOT NULL,
  vote_count INT NOT NULL DEFAULT 0,
  FOREIGN KEY (poll_id) REFERENCES community_polls(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE community_poll_votes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  poll_id INT NOT NULL,
  option_id INT NOT NULL,
  user_id INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (poll_id) REFERENCES community_polls(id) ON DELETE CASCADE,
  FOREIGN KEY (option_id) REFERENCES community_poll_options(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_poll_voter (poll_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE community_saved_posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  post_id INT NOT NULL,
  user_id INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_saved (post_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE community_reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reportable_type ENUM('post','comment') NOT NULL,
  reportable_id INT NOT NULL,
  reporter_id INT NOT NULL,
  reason VARCHAR(500) NOT NULL,
  status ENUM('pending','reviewed','dismissed') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_follows (
  id INT AUTO_INCREMENT PRIMARY KEY,
  follower_id INT NOT NULL,
  followed_id INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (followed_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_follow (follower_id, followed_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE study_groups (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(191) NOT NULL,
  slug VARCHAR(191) NOT NULL UNIQUE,
  description TEXT NULL,
  privacy ENUM('public','private') NOT NULL DEFAULT 'public',
  owner_id INT NOT NULL,
  meet_link VARCHAR(500) NULL,
  zoom_link VARCHAR(500) NULL,
  schedule_text VARCHAR(255) NULL,
  member_count INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE study_group_members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  group_id INT NOT NULL,
  user_id INT NOT NULL,
  role ENUM('member','owner') NOT NULL DEFAULT 'member',
  joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (group_id) REFERENCES study_groups(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_group_member (group_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE study_group_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  group_id INT NOT NULL,
  author_id INT NOT NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (group_id) REFERENCES study_groups(id) ON DELETE CASCADE,
  FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_group_created (group_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 1:1 direct messages only for v1 — group DMs beyond study-group chat are a
-- later-phase feature, not modeled here.
CREATE TABLE conversations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE conversation_participants (
  id INT AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT NOT NULL,
  user_id INT NOT NULL,
  FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_participant (conversation_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT NOT NULL,
  sender_id INT NOT NULL,
  body TEXT NOT NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_conversation_created (conversation_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User-facing social notifications — deliberately separate from
-- admin_notifications (a different concern: platform-ops alerts vs. social
-- activity), not a rename/reuse of it.
CREATE TABLE user_notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type ENUM('like','comment','mention','follow','reply','message') NOT NULL,
  message VARCHAR(500) NOT NULL,
  link_url VARCHAR(500) NULL,
  related_id INT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_read (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE users
  ADD COLUMN xp_points INT NOT NULL DEFAULT 0,
  ADD COLUMN current_streak INT NOT NULL DEFAULT 0,
  ADD COLUMN longest_streak INT NOT NULL DEFAULT 0,
  ADD COLUMN last_active_date DATE NULL,
  ADD COLUMN skills VARCHAR(500) NULL,
  ADD COLUMN looking_for VARCHAR(255) NULL;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------------
-- Starter categories
INSERT INTO categories (name, slug, icon) VALUES
  ('Business', 'business', 'briefcase'),
  ('Finance', 'finance', 'wallet'),
  ('Technology & Software Development', 'technology-software-development', 'code'),
  ('Marketing & Digital Marketing', 'marketing-digital-marketing', 'megaphone'),
  ('Health & Wellness', 'health-wellness', 'heart'),
  ('Agriculture', 'agriculture', 'sprout'),
  ('Education & Teaching', 'education-teaching', 'graduation-cap'),
  ('Design & Creative', 'design-creative', 'palette'),
  ('Ecommerce', 'ecommerce', 'shopping-cart'),
  ('Artificial Intelligence', 'artificial-intelligence', 'cpu');
