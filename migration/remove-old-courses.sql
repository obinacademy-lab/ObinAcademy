-- Obin Academy is campuses only: remove every course from the old per-course "Discover Schools"
-- version, and make every creator a learner. PERMANENT. Take a full export first.
--
-- Deleting courses also removes, through the database's own ON DELETE CASCADE rules: their
-- modules and lessons, enrolments and progress, certificates, course payments, course earnings,
-- instalment plans, reviews, comments, gifts, course coupons, course communities, views, shares and
-- interest. Campus payments, memberships and everything campus-related are not touched.
-- Decided 2026-10-13: course payment history is deleted too, and creator balances are written off.

START TRANSACTION;

-- 1. Every course, and everything that hangs off one.
DELETE FROM courses;

-- 2. What is left of the old model that is not tied to a single course.
DELETE FROM earnings;
DELETE FROM coupons;
DELETE FROM bundles;
DELETE FROM school_subscriptions;

-- 3. Every creator becomes a learner. Admins are unchanged.
UPDATE users SET role = 'LEARNER' WHERE role = 'CREATOR';

COMMIT;
