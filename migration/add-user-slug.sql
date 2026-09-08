-- Gives creators a pretty, shareable "School" URL (school.php?slug=...).
-- Nullable — only creators end up with one, generated lazily the first
-- time it's needed (see ensure_creator_slug() in includes/functions.php).

ALTER TABLE users
  ADD COLUMN slug VARCHAR(191) NULL UNIQUE AFTER name;
