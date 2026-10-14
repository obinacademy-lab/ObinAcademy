-- Professor-paced months and a picture per month.
-- Before: each student's months opened on a timer (month 2 thirty days after they joined, and so on).
-- After: the whole campus moves together. Students see month 1; when the professor marks a month
-- complete, the next month opens for every student in that campus. A student who joins later gets
-- every month the class has already reached. Each of the twelve months can have its own thumbnail.
-- Run in phpMyAdmin with the Obin Academy database selected. Safe to run more than once.

-- How many months the professor has completed (0 = the class is on month 1).
ALTER TABLE campuses ADD COLUMN IF NOT EXISTS months_completed TINYINT NOT NULL DEFAULT 0;

-- A picture for each month, and when the professor completed it.
ALTER TABLE campus_stages ADD COLUMN IF NOT EXISTS thumbnail_url VARCHAR(500) NULL;
ALTER TABLE campus_stages ADD COLUMN IF NOT EXISTS completed_at DATETIME NULL;
