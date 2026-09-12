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
  -- Opt-out for retention nudge emails specifically (see retention.php) —
  -- separate from leads.unsubscribed, which governs a different audience
  -- (pre-signup marketing emails) via a different token/table entirely.
  retention_emails_opt_out TINYINT(1) NOT NULL DEFAULT 0,
  -- Separate from retention_emails_opt_out — a learner/creator can opt out
  -- of "a new course just went live" broadcasts independently of inactivity
  -- nudges. See includes/course_notify.php.
  new_course_emails_opt_out TINYINT(1) NOT NULL DEFAULT 0,
  -- NULL means "use this role's default" (see dashboard_theme_default() in
  -- functions.php) rather than baking a literal default in here — so
  -- changing a role's default later doesn't require touching existing rows.
  -- One of: purple, gold, red, green, blue, slate.
  dashboard_theme_color VARCHAR(20) NULL,
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
  -- EVENT rows are one-off ticketed events (workshops, webinars, meetups)
  -- sold through the exact same courses/payments/enrollments pipeline as a
  -- COURSE — see the event_* columns below and course_has_active_sale()'s
  -- sibling event_has_passed()/event_is_sold_out() in functions.php. Default
  -- keeps every pre-existing row a course with no backfill needed.
  type ENUM('COURSE','EVENT') NOT NULL DEFAULT 'COURSE',
  summary VARCHAR(500) NOT NULL,
  description TEXT NOT NULL,
  thumbnail_url VARCHAR(500) NULL,
  price DECIMAL(12,2) NOT NULL DEFAULT 0,
  sale_price DECIMAL(12,2) NULL,
  -- NULL = the sale has no expiry (today's existing behavior). Once set, the
  -- sale price automatically stops applying past this moment everywhere it's
  -- read (course_has_active_sale() in includes/functions.php) — no cron job
  -- clears sale_price/sale_ends_at, the timestamp itself is the source of truth.
  sale_ends_at DATETIME NULL,
  access_duration_days INT NULL,
  premium_price DECIMAL(12,2) NULL,
  -- Event-only fields — always NULL for type='COURSE'. Whether an event is
  -- in-person, online, or hybrid is inferred from which of
  -- event_location/event_online_url is set, rather than a separate flag.
  -- ticket_capacity NULL = unlimited; "tickets sold" is a live
  -- COUNT(enrollments), the same pattern student_count already uses, so
  -- there's no separate counter column that could drift.
  event_starts_at DATETIME NULL,
  event_ends_at DATETIME NULL,
  event_location VARCHAR(255) NULL,
  event_online_url VARCHAR(500) NULL,
  ticket_capacity INT NULL,
  -- Optional second ticket tier. NULL vip_price = no VIP tier offered (the
  -- event only sells the plain "Ordinary" tier above). VIP has no separate
  -- sale-price mechanism and, like ticket_capacity, NULL vip_capacity means
  -- unlimited VIP tickets.
  vip_price DECIMAL(12,2) NULL,
  vip_capacity INT NULL,
  view_count INT NOT NULL DEFAULT 0,
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
  INDEX idx_courses_creator (creator_id),
  INDEX idx_courses_type_starts (type, event_starts_at)
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
  -- Stamped on enrollment and bumped on every update_lesson_progress() call —
  -- the signal the learner-retention cron sweep uses to detect inactivity.
  last_activity_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  enrolled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NULL,
  is_premium TINYINT(1) NOT NULL DEFAULT 0,
  user_id INT NULL,
  guest_name VARCHAR(191) NULL,
  guest_email VARCHAR(191) NULL,
  access_token_hash VARCHAR(64) NULL,
  course_id INT NOT NULL,
  -- Which ticket tier this enrollment is for — always ORDINARY for a
  -- COURSE row (the default), meaningful only for EVENT rows that offer a
  -- VIP tier via courses.vip_price.
  ticket_tier ENUM('ORDINARY','VIP') NOT NULL DEFAULT 'ORDINARY',
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_user_course (user_id, course_id),
  UNIQUE KEY uniq_access_token_hash (access_token_hash),
  INDEX idx_enrollments_activity (user_id, last_activity_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- One row per retention nudge actually sent, so the cron sweep never repeats
-- the same stage twice for one inactivity episode, and picks a template the
-- learner hasn't seen the last time this stage fired for them. sent_at is
-- compared against the enrollment's CURRENT last_activity_at at query time —
-- once a learner returns and last_activity_at moves forward, every past row
-- here reads as "before their last activity" again, so the stage becomes
-- eligible again next time they go quiet. That's the whole reset mechanism;
-- there's no separate "reset timer" step.
CREATE TABLE retention_notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  stage VARCHAR(10) NOT NULL,
  template_key VARCHAR(30) NOT NULL,
  sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  user_id INT NOT NULL,
  enrollment_id INT NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE,
  INDEX idx_retention_enrollment_stage (enrollment_id, stage),
  INDEX idx_retention_user (user_id)
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
  -- Which ticket tier was paid for — set at initiate_payment() time and
  -- copied onto the resulting enrollment on success; NULL for course
  -- purchases and premium upgrades, where tiers don't apply.
  ticket_tier ENUM('ORDINARY','VIP') NULL,
  -- How many tickets this one purchase covers (always 1 for a course) — the
  -- buyer's own enrollment plus quantity-1 extra standalone guest-style
  -- tickets created on success, one per name in extra_attendees (a JSON
  -- array, blank names defaulting to "Guest N" at creation time).
  quantity INT NOT NULL DEFAULT 1,
  extra_attendees TEXT NULL,
  -- Captured at initiate_payment() time from the oa_aff attribution cookie
  -- (see includes/affiliates.php), not re-resolved later — so a payment
  -- keeps the affiliate who was actually credited at checkout even if that
  -- affiliate's status changes afterward.
  affiliate_id INT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE SET NULL,
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
-- payee_type says which of creator_id/affiliate_id is the one that's set —
-- app logic (not a DB constraint) always sets exactly one, same convention
-- as enrollments' user_id-vs-guest_email split elsewhere in this schema.
CREATE TABLE withdrawal_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  amount DECIMAL(12,2) NOT NULL,
  phone VARCHAR(32) NOT NULL DEFAULT '',
  payee_type ENUM('CREATOR','AFFILIATE') NOT NULL DEFAULT 'CREATOR',
  status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
  note VARCHAR(500) NULL,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  creator_id INT NULL,
  affiliate_id INT NULL,
  FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Affiliate program. Affiliate status is independent of role — a LEARNER
-- (or any user) applies here, an admin approves/rejects the same way
-- creator_applications works, and approval creates one row in `affiliates`
-- immediately (with its ref_code) rather than changing the user's role.
CREATE TABLE affiliate_applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
  motivation TEXT NOT NULL,
  rejection_reason TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ref_code is the one stable link every affiliate shares platform-wide
-- (?aff=<ref_code> on any page, not tied to one course — see
-- includes/affiliates.php's attribution cookie) — generated the moment an
-- application is approved, per the program's "link ready immediately" rule.
CREATE TABLE affiliates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ref_code VARCHAR(20) NOT NULL UNIQUE,
  status ENUM('ACTIVE','SUSPENDED') NOT NULL DEFAULT 'ACTIVE',
  approved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  user_id INT NOT NULL UNIQUE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per affiliate-attributed sale's 2% cut — mirrors `earnings`
-- (the creator-side equivalent) but for the affiliate side of the same
-- payment. UNIQUE on payment_id guards against ever double-crediting one
-- payment if resolve_payment_with_iotec() is somehow invoked twice for it.
CREATE TABLE affiliate_earnings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  amount DECIMAL(12,2) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  affiliate_id INT NOT NULL,
  course_id INT NOT NULL,
  payment_id INT NOT NULL,
  FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_affiliate_payment (payment_id)
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
-- One row per successful sign-in (role stored at the time of login, since a
-- user's role can change later and the historical record should reflect what
-- they were then). country/city are resolved after the fact by the same
-- cron geo sweep that backfills visitor_sessions — never looked up on the
-- request path — and ip_address is cleared the moment that resolution runs
-- (or gives up), so it's never kept longer than one sweep needs it for.
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

-- ---------------------------------------------------------------------------
-- One row per share-button click (not per page view — only actual shares).
-- share_token is embedded in the URL that channel actually sends out, so a
-- visit back on courses/view.php?...&ref=<token> can be attributed to this
-- exact share. sharer_id is null for a guest/logged-out sharer.
CREATE TABLE course_shares (
  id INT AUTO_INCREMENT PRIMARY KEY,
  channel VARCHAR(20) NOT NULL,
  share_token VARCHAR(16) NOT NULL UNIQUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  course_id INT NOT NULL,
  sharer_id INT NULL,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  FOREIGN KEY (sharer_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_course_shares_course (course_id),
  INDEX idx_course_shares_token (share_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per visit that arrives carrying a share_token — how a single share
-- link's reach is measured. A share visited by only one distinct visitor_id
-- reads as a direct, single-recipient share; the same token visited by many
-- distinct visitor_ids is evidence the link left that one recipient's hands
-- and got passed around further (or posted somewhere public).
CREATE TABLE course_share_visits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  visitor_id VARCHAR(32) NULL,
  visited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  share_id INT NOT NULL,
  FOREIGN KEY (share_id) REFERENCES course_shares(id) ON DELETE CASCADE,
  INDEX idx_csv_share (share_id)
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
-- Open discussion on a course or event — unlike reviews, posting needs an
-- account but not enrollment/a ticket. status=HIDDEN is set automatically
-- at submission time by comment_contains_blocked_language() in
-- includes/moderation.php and never shown publicly; kept (not deleted)
-- so an admin can review/restore a false positive at dashboard/admin/comments.php.
CREATE TABLE comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  body TEXT NOT NULL,
  status ENUM('VISIBLE','HIDDEN') NOT NULL DEFAULT 'VISIBLE',
  hidden_reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  user_id INT NOT NULL,
  course_id INT NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  INDEX idx_comments_course_status (course_id, status, created_at)
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
