-- Payment plans (2 installments) become the creator's choice, off by default.
--
-- Until now every paid course offered the plan automatically, and the
-- installments_enabled column was saved but never read. After this deploy the
-- column is the real switch (see course_offers_installments() in
-- includes/installments.php). This script sets it correctly for existing courses.
--
-- A creator "set" a payment plan if they chose their own first amount
-- (first_installment_amount) or a non-default gap between payments
-- (installment_interval_days <> 14). Everyone else never made a choice, so
-- their courses go back to payment in full. They can switch the plan on again
-- from Edit Course Details. Learners already in a plan are NOT affected: plans
-- live in installment_plans and keep running whatever this column says.
--
-- Run STEP 1 first, read the result, then run STEP 2.

-- ---------------------------------------------------------------------------
-- STEP 1 — PREVIEW (changes nothing)
-- ---------------------------------------------------------------------------
SELECT
  u.name  AS creator,
  c.id,
  c.title,
  c.status,
  c.price,
  c.installments_enabled      AS plan_on_now,
  c.first_installment_amount  AS first_amount_set,
  c.installment_interval_days AS gap_days,
  (SELECT COUNT(*) FROM installment_plans ip WHERE ip.course_id = c.id) AS learners_in_plans,
  CASE
    WHEN c.first_installment_amount IS NOT NULL OR c.installment_interval_days <> 14
      THEN 'KEEP: creator set it'
    ELSE 'TURN OFF: never set'
  END AS decision
FROM courses c
JOIN users u ON u.id = c.creator_id
WHERE c.price > 0
ORDER BY decision, u.name, c.title;

-- ---------------------------------------------------------------------------
-- STEP 2 — APPLY
-- ---------------------------------------------------------------------------
-- Courses where the creator never chose: payment in full.
UPDATE courses
SET installments_enabled = 0
WHERE first_installment_amount IS NULL
  AND installment_interval_days = 14;

-- Courses where the creator did choose: keep offering the plan.
UPDATE courses
SET installments_enabled = 1,
    installment_count = 2
WHERE price > 0
  AND (first_installment_amount IS NOT NULL OR installment_interval_days <> 14);

-- ---------------------------------------------------------------------------
-- STEP 3 — CHECK (after applying)
-- ---------------------------------------------------------------------------
SELECT
  SUM(installments_enabled = 1) AS courses_offering_plan,
  SUM(installments_enabled = 0) AS courses_paid_in_full
FROM courses
WHERE price > 0;
