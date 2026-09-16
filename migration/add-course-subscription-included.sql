-- Lets a creator whose school is in subscription mode still sell specific
-- courses separately (e.g. a flagship/premium course priced above what
-- feels right to fold into the base monthly fee) — see
-- dashboard/creator/course-new.php, course-manage.php, and
-- includes/enroll_panel.php. Irrelevant/unused for a PER_COURSE school,
-- where every course is always sold individually regardless of this flag.
-- Defaults to 1 (included) so existing courses under an already-subscription
-- creator keep behaving exactly as they do today.
ALTER TABLE courses
  ADD COLUMN subscription_included TINYINT(1) NOT NULL DEFAULT 1 AFTER premium_price;
