-- The professor's social media links and a short quote, shown on the public campus page.
-- Set by Obin Academy (Admin, Campuses, Professor tab) or by the professor (Campus Studio).
-- Run in phpMyAdmin with the Obin Academy database selected. Safe to run more than once.

ALTER TABLE campuses ADD COLUMN IF NOT EXISTS professor_quote VARCHAR(300) NULL;
ALTER TABLE campuses ADD COLUMN IF NOT EXISTS professor_instagram VARCHAR(255) NULL;
ALTER TABLE campuses ADD COLUMN IF NOT EXISTS professor_tiktok VARCHAR(255) NULL;
ALTER TABLE campuses ADD COLUMN IF NOT EXISTS professor_youtube VARCHAR(255) NULL;
ALTER TABLE campuses ADD COLUMN IF NOT EXISTS professor_x VARCHAR(255) NULL;
ALTER TABLE campuses ADD COLUMN IF NOT EXISTS professor_linkedin VARCHAR(255) NULL;
ALTER TABLE campuses ADD COLUMN IF NOT EXISTS professor_facebook VARCHAR(255) NULL;
ALTER TABLE campuses ADD COLUMN IF NOT EXISTS professor_whatsapp VARCHAR(32) NULL;
