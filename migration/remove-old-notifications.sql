-- Removes every notification left over from the old course platform (comment replies, likes, course
-- comments, sales, withdrawals and the like) and keeps only the campus ones. Run in phpMyAdmin with the
-- Obin Academy database selected. Safe to run more than once. This deletes rows for good.

-- Members' and professors' bell: keep only campus notifications.
DELETE FROM user_notifications
WHERE type NOT IN (
  'campus_joined', 'campus_renewal', 'campus_month_open', 'campus_year_done', 'campus_certificate',
  'campus_chat', 'campus_reply', 'campus_comment',
  'professor_earning', 'professor_paid', 'payout_declined'
);

-- Admin bell: keep payment requests, teach applications, leads and visitor alerts.
DELETE FROM admin_notifications
WHERE type NOT IN (
  'payout_request', 'instructor_application', 'new_lead', 'pricing_revisit', 'stale_returning_visitor'
);
