-- Removes the Community module entirely: all its tables and the columns it
-- added to `users`. Run this ONCE in phpMyAdmin, and only AFTER deploying
-- the corresponding code removal and confirming the live site works —
-- these tables/columns are unused by that point, so dropping them is safe,
-- but do this only after the code that stops referencing them is live.

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS
  community_poll_votes,
  community_poll_options,
  community_polls,
  community_comment_likes,
  community_comments,
  community_post_likes,
  community_saved_posts,
  community_reports,
  community_posts,
  community_categories,
  community_members,
  communities,
  user_follows,
  study_group_messages,
  study_group_members,
  study_groups,
  messages,
  conversation_participants,
  conversations,
  user_notifications;

SET FOREIGN_KEY_CHECKS = 1;

ALTER TABLE users
  DROP COLUMN xp_points,
  DROP COLUMN current_streak,
  DROP COLUMN longest_streak,
  DROP COLUMN last_active_date,
  DROP COLUMN skills,
  DROP COLUMN looking_for;
