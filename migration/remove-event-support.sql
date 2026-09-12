-- Reverses add-event-support.sql / add-vip-ticket-tier.sql / add-ticket-quantity.sql.
-- The platform is going back to courses-only. Deletes any existing EVENT
-- rows (and everything that cascades from them — enrollments, payments,
-- earnings, affiliate_earnings, certificates, course_shares, reviews,
-- comments) before dropping the now-unused columns, since a column can't be
-- dropped out from under data that still depends on it meaning something.
-- withdrawal_requests is untouched — money already approved/paid out stays
-- paid out, it does not key on course_id.

DELETE FROM courses WHERE type = 'EVENT';

ALTER TABLE courses
  DROP INDEX idx_courses_type_starts,
  DROP COLUMN type,
  DROP COLUMN event_starts_at,
  DROP COLUMN event_ends_at,
  DROP COLUMN event_location,
  DROP COLUMN event_online_url,
  DROP COLUMN ticket_capacity,
  DROP COLUMN vip_price,
  DROP COLUMN vip_capacity;

ALTER TABLE enrollments
  DROP COLUMN ticket_tier;

ALTER TABLE payments
  DROP COLUMN ticket_tier,
  DROP COLUMN quantity,
  DROP COLUMN extra_attendees;
