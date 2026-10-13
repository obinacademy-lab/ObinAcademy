-- Removes every table left from the old per-course platform (courses, creators, schools, affiliates,
-- communities, messaging), now that the site runs only as campuses. PERMANENT: export a backup first.
-- Run AFTER the campus-only code is live (no page reads these tables any more) and after
-- migration/remove-old-courses.sql. Kept: users, payments, campuses and campus_*, professor pay,
-- instructor_applications, leads and lead_*, testimonials, notifications, analytics, login_log, audit_log.

SET FOREIGN_KEY_CHECKS = 0;

-- payments keeps its history columns but no longer points at the old tables.
ALTER TABLE payments
  DROP FOREIGN KEY payments_ibfk_2,
  DROP FOREIGN KEY payments_ibfk_3,
  DROP FOREIGN KEY payments_ibfk_4,
  DROP FOREIGN KEY payments_ibfk_5,
  DROP FOREIGN KEY payments_ibfk_7,
  DROP FOREIGN KEY payments_ibfk_8,
  DROP FOREIGN KEY payments_ibfk_9,
  DROP FOREIGN KEY payments_ibfk_10;

DROP TABLE IF EXISTS
  affiliate_earnings, affiliate_applications, affiliates,
  bundle_courses, bundles, coupon_redemptions, coupons, course_gifts, installment_plans,
  school_subscriptions, subscriptions, creator_subscription_payouts, earnings, withdrawal_requests,
  certificates, reviews, comment_likes, comments, course_interest, course_share_visits, course_shares, course_views,
  lesson_text_completions, lesson_watch_events, retention_notifications, enrollments, lessons, modules, courses, categories,
  community_saved_posts, community_reports, community_post_likes, community_poll_votes, community_poll_options, community_polls,
  community_comment_likes, community_comments, community_posts, community_members, community_categories, communities,
  study_group_messages, study_group_members, study_groups, messages, conversation_participants, conversations,
  creator_applications, school_follows, user_follows, broadcasts, campus_payouts;

SET FOREIGN_KEY_CHECKS = 1;
