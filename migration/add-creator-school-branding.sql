-- Lets each creator brand their own public page as their own school:
-- a custom school name (falls back to "{Name}'s School" when unset) and an
-- optional cover banner image, both editable from dashboard/settings.php.
ALTER TABLE users
  ADD COLUMN school_name VARCHAR(160) NULL AFTER headline,
  ADD COLUMN school_cover_url VARCHAR(500) NULL AFTER avatar_url;
