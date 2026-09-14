-- Simplify subscription tiers from Go/Plus/Pro to Go/Pro, and retire the
-- Plus tier's ENUM value now that one-time course purchases are gone and
-- every course requires a subscription. Only safe to run if no PLUS rows
-- exist yet — check first:
--   SELECT COUNT(*) FROM subscriptions WHERE tier='PLUS';
--   SELECT COUNT(*) FROM payments WHERE subscription_tier='PLUS';
-- Both should be 0 (this feature only just shipped).

ALTER TABLE subscriptions
  MODIFY COLUMN tier ENUM('GO','PRO') NOT NULL;

ALTER TABLE payments
  MODIFY COLUMN subscription_tier ENUM('GO','PRO') NULL;
