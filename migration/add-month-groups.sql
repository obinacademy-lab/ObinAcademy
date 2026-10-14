-- Groups by month: every student has their own place in the year.
-- Before: the whole campus moved together, so a new student joined at the month the class was on.
-- After: every new student starts at month 1. Students on the same month form a group, and the
-- professor completes each group's month separately. Completing a month takes effect 10 minutes
-- later (next_month_at), so the professor can undo it before students see anything.
-- Run in phpMyAdmin with the Obin Academy database selected. Safe to run more than once.

ALTER TABLE campus_memberships ADD COLUMN IF NOT EXISTS month_open TINYINT NOT NULL DEFAULT 1;
ALTER TABLE campus_memberships ADD COLUMN IF NOT EXISTS next_month_at DATETIME NULL;

-- Students who joined before this change keep every month they can already open.
UPDATE campus_memberships m JOIN campuses c ON c.id = m.campus_id
   SET m.month_open = LEAST(12, c.months_completed + 1)
 WHERE m.month_open = 1 AND c.months_completed > 0;
