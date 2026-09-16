-- Distinguishes a real one-time purchase (kept forever, grandfathered even
-- if the creator later switches their school to subscription pricing) from
-- an enrollments row created only because the learner has an active school
-- subscription (access lazily granted the first time they open the course
-- via learn.php — see public/learn.php). A SUBSCRIPTION-sourced row's
-- access is re-checked live against the subscription's current status on
-- every visit; a PURCHASE-sourced row's access never depends on it.
ALTER TABLE enrollments
  ADD COLUMN source ENUM('PURCHASE','SUBSCRIPTION') NOT NULL DEFAULT 'PURCHASE' AFTER is_premium;
