<?php
/**
 * Community module — Phase 6 (basic gamification): XP points and daily
 * login/activity streaks on top of the xp_points/current_streak/
 * longest_streak/last_active_date columns added to users in Phase 1.
 * Deliberately basic per the plan — badges, levels, and leaderboards are
 * explicitly out of v1 scope.
 *
 * Required from bootstrap.php (not community.php) since record_daily_activity()
 * is called from login_user() in auth.php, which runs on every login
 * regardless of whether the page also loads the community module.
 */

function award_xp(int $userId, int $amount): void {
    db_run('UPDATE users SET xp_points = xp_points + ? WHERE id = ?', [$amount, $userId]);
}

/**
 * Idempotent per calendar day — a repeat call today is a no-op. Extends the
 * streak by 1 if the user was also active yesterday, otherwise resets it to
 * 1 (a missed day breaks the streak). Call from any genuine activity signal
 * (login, posting, commenting).
 */
function record_daily_activity(int $userId): void {
    $user = db_one('SELECT last_active_date, current_streak, longest_streak FROM users WHERE id = ?', [$userId]);
    if (!$user) return;

    $today = date('Y-m-d');
    if ($user['last_active_date'] === $today) return;

    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $newStreak = ($user['last_active_date'] === $yesterday) ? (int) $user['current_streak'] + 1 : 1;
    $newLongest = max($newStreak, (int) $user['longest_streak']);

    db_run('UPDATE users SET current_streak = ?, longest_streak = ?, last_active_date = ? WHERE id = ?', [$newStreak, $newLongest, $today, $userId]);
}
