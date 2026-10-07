-- Creator applications now ask for a public social profile (TikTok / YouTube / Instagram...)
-- so an admin can check who is applying. Additive and safe to run once; until it is run, the
-- application form still works and keeps the link inside the "motivation" text.
ALTER TABLE creator_applications
  ADD COLUMN social_link VARCHAR(500) NULL AFTER motivation;
