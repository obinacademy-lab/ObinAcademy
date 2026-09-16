-- Locks a school subscription to a single course at a time, instead of
-- unlocking every course a MONTHLY_SUBSCRIPTION creator publishes for one
-- payment. A learner now pays for and accesses exactly the course they
-- subscribed through; opening a different course from the same creator
-- means subscribing again, which switches the one active course_id on
-- their existing school_subscriptions row (still one row per
-- learner+creator, per uniq_learner_creator) rather than granting
-- all-access. See includes/school_subscriptions.php.
ALTER TABLE school_subscriptions
  ADD COLUMN course_id INT NULL AFTER creator_id,
  ADD FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL;

-- One-time backfill for subscribers who joined under the old all-access
-- model, which never recorded which course they subscribed through. Best
-- available proxy: the earliest SUBSCRIPTION-sourced enrollment for that
-- learner+creator pair (the first course access was auto-granted for),
-- since that's most likely the course they originally tapped "Subscribe"
-- from. Only touches rows that don't already have a course_id.
UPDATE school_subscriptions ss
JOIN (
  SELECT e.user_id AS learner_id, c.creator_id, MIN(e.id) AS first_enrollment_id
  FROM enrollments e
  JOIN courses c ON c.id = e.course_id
  WHERE e.source = 'SUBSCRIPTION'
  GROUP BY e.user_id, c.creator_id
) fe ON fe.learner_id = ss.learner_id AND fe.creator_id = ss.creator_id
JOIN enrollments e2 ON e2.id = fe.first_enrollment_id
SET ss.course_id = e2.course_id
WHERE ss.course_id IS NULL;
