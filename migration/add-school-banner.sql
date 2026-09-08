-- Lets creators upload a big banner image for their School page, shown at
-- the top of school.php and as a preview image on schools.php's directory
-- cards. Nullable — falls back to the existing plain gradient banner.

ALTER TABLE users
  ADD COLUMN school_banner_url VARCHAR(500) NULL AFTER avatar_url;
