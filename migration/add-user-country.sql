-- Opens up signup/profile to learners and creators outside Uganda. Payment
-- (mobile money via iotec) stays Uganda-only for now — this only adds a
-- country field for signup/profile/settings. Existing rows default to 'UG'
-- since that's the only market that existed before this.

ALTER TABLE users
  ADD COLUMN country CHAR(2) NOT NULL DEFAULT 'UG' AFTER phone;
