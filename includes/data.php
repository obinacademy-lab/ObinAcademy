<?php

/**
 * Every learner enrolled in one of $creatorId's own courses — creator-only,
 * never rendered on a public page (unlike school_follows, enrolling/paying
 * is private the way it is on every other course platform: Udemy, Coursera,
 * Skool included). A guest enrollment (no account) shows its captured
 * guest_name/guest_email instead of a joined user row.
 */
function get_students_for_creator(int $creatorId): array {
    return db_all(
        "SELECT e.id, e.progress, e.enrolled_at, e.expires_at, e.source, e.is_premium,
                c.id AS course_id, c.title AS course_title,
                COALESCE(u.name, e.guest_name) AS learner_name,
                COALESCE(u.email, e.guest_email) AS learner_email,
                u.id AS learner_user_id, u.avatar_url AS learner_avatar_url
         FROM enrollments e
         JOIN courses c ON c.id = e.course_id
         LEFT JOIN users u ON u.id = e.user_id
         WHERE c.creator_id = ?
         ORDER BY e.enrolled_at DESC",
        [$creatorId]
    );
}

function get_categories(): array {
    return db_all('SELECT * FROM categories ORDER BY name ASC');
}

/** Course rows (+ category name, creator name/avatar, student count, avg rating) for card rendering. */
function get_course_cards(string $whereSql = '', array $params = [], string $orderBy = 'c.created_at DESC', ?int $limit = null, ?int $offset = null): array {
    $sql = "
        SELECT c.*, cat.name AS category_name, u.name AS creator_name, u.avatar_url AS creator_avatar_url,
          u.pricing_model AS creator_pricing_model, u.school_monthly_price AS creator_school_monthly_price,
          (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS student_count,
          (SELECT COALESCE(AVG(r.rating), 0) FROM reviews r WHERE r.course_id = c.id) AS avg_rating,
          (SELECT COUNT(*) FROM reviews r WHERE r.course_id = c.id) AS review_count
        FROM courses c
        JOIN categories cat ON cat.id = c.category_id
        JOIN users u ON u.id = c.creator_id
        WHERE c.status = 'PUBLISHED' " . ($whereSql ? "AND $whereSql" : '') . "
        ORDER BY $orderBy
    ";
    if ($limit) {
        $sql .= " LIMIT $limit";
        if ($offset) $sql .= " OFFSET $offset";
    }
    return db_all($sql, $params);
}

function get_featured_courses(int $take = 6): array {
    return get_course_cards('', [], POPULARITY_ORDER, $take);
}

// Ranks by view_count first, then by student_count (enrollments/purchases) as
// the tiebreaker — the most-viewed course leads even if a lower-viewed course
// has more purchases, and among courses with the same view count the
// most-bought one wins. created_at is the final tiebreak for stability.
const POPULARITY_ORDER = 'c.view_count DESC, student_count DESC, c.created_at DESC';

const COURSE_SORT_OPTIONS = [
    'newest' => ['label' => 'Newest', 'order' => 'c.created_at DESC'],
    'popular' => ['label' => 'Most Popular', 'order' => POPULARITY_ORDER],
    'rating' => ['label' => 'Highest Rated', 'order' => 'avg_rating DESC, review_count DESC'],
    'price_low' => ['label' => 'Price: Low to High', 'order' => 'c.price ASC'],
    'price_high' => ['label' => 'Price: High to Low', 'order' => 'c.price DESC'],
];

/** @param string $price '' (any), 'free', or 'paid' — anything else is ignored. */
function search_courses(string $query = '', string $categorySlug = '', string $sort = 'popular', string $price = ''): array {
    $where = [];
    $params = [];
    if ($categorySlug) {
        $where[] = 'cat.slug = ?';
        $params[] = $categorySlug;
    }
    if ($query) {
        $where[] = '(c.title LIKE ? OR c.summary LIKE ?)';
        $params[] = "%$query%";
        $params[] = "%$query%";
    }
    if ($price === 'free') {
        $where[] = 'c.price <= 0';
    } elseif ($price === 'paid') {
        $where[] = 'c.price > 0';
    }
    $orderBy = COURSE_SORT_OPTIONS[$sort]['order'] ?? COURSE_SORT_OPTIONS['popular']['order'];
    return get_course_cards(implode(' AND ', $where), $params, $orderBy);
}

/**
 * Real recent activity — enrollments, completions, and reviews mixed
 * together — for the "recent activity" social-proof toast (see
 * courses/view.php and includes/footer.php). Never fabricated. $courseId
 * scopes to one course (the course page's own toast); null means the whole
 * platform, any published course (the site-wide toast). Each row carries a
 * uniform shape — type, learner_name, city, at, action (a ready-to-display
 * verb phrase, including the star count for a review), course_title,
 * course_slug — so a caller never needs to branch on $row['type'] itself.
 *
 * City is the learner's most recently known login location, same "latest
 * known location" idiom as LEAD_LOCATION_SUBQUERY/
 * get_top_learner_locations_for_creator — null when never resolved, and the
 * toast just omits it rather than guessing. "Completed" uses last_activity_at
 * as its timestamp — enrollments has no dedicated completed_at column, and
 * for a learner sitting at 100% progress, the last time they touched the
 * course is a reasonable real-data stand-in for when they finished it.
 */
function get_recent_activity_feed(?int $courseId = null, int $limit = 8): array {
    $limit = max(1, min(15, $limit));
    $fetchLimit = $limit * 2; // over-fetch each type before merging, so the mix isn't dominated by whichever type's own query happens to sort first
    $courseFilter = $courseId !== null ? 'AND c.id = ?' : '';
    $params = $courseId !== null ? [$courseId] : [];
    $citySub = fn(string $userCol) => "(SELECT l.city FROM login_log l WHERE l.user_id = $userCol AND l.city IS NOT NULL ORDER BY l.logged_in_at DESC LIMIT 1)";

    $enrollPhrases = ["'just enrolled in'", "'just joined'", "'just signed up for'"];
    $enrollAction = 'ELT(1 + (e.id MOD ' . count($enrollPhrases) . '), ' . implode(', ', $enrollPhrases) . ')';

    $enrollments = db_all(
        "SELECT 'enrolled' AS type, e.enrolled_at AS at, COALESCE(u.name, e.guest_name) AS learner_name,
                {$citySub('e.user_id')} AS city, $enrollAction AS action,
                c.title AS course_title, c.slug AS course_slug
         FROM enrollments e
         JOIN courses c ON c.id = e.course_id
         LEFT JOIN users u ON u.id = e.user_id
         WHERE c.status = 'PUBLISHED' AND COALESCE(u.name, e.guest_name) IS NOT NULL $courseFilter
         ORDER BY e.enrolled_at DESC LIMIT $fetchLimit",
        $params
    );

    $completions = db_all(
        "SELECT 'completed' AS type, e.last_activity_at AS at, COALESCE(u.name, e.guest_name) AS learner_name,
                {$citySub('e.user_id')} AS city, 'just completed' AS action,
                c.title AS course_title, c.slug AS course_slug
         FROM enrollments e
         JOIN courses c ON c.id = e.course_id
         LEFT JOIN users u ON u.id = e.user_id
         WHERE c.status = 'PUBLISHED' AND e.progress >= 100 AND COALESCE(u.name, e.guest_name) IS NOT NULL $courseFilter
         ORDER BY e.last_activity_at DESC LIMIT $fetchLimit",
        $params
    );

    $reviews = db_all(
        "SELECT 'reviewed' AS type, r.created_at AS at, u.name AS learner_name,
                {$citySub('r.author_id')} AS city, CONCAT('left a ', r.rating, '-star review on') AS action,
                c.title AS course_title, c.slug AS course_slug
         FROM reviews r
         JOIN courses c ON c.id = r.course_id
         JOIN users u ON u.id = r.author_id
         WHERE c.status = 'PUBLISHED' $courseFilter
         ORDER BY r.created_at DESC LIMIT $fetchLimit",
        $params
    );

    $all = array_merge($enrollments, $completions, $reviews);
    usort($all, fn($a, $b) => strtotime($b['at']) <=> strtotime($a['at']));
    return array_slice($all, 0, $limit);
}

/**
 * Real distinct visitors who loaded this course's page in the last
 * $windowMinutes — for a "N people viewing this course right now" urgency
 * badge on the enroll panel. Sourced from visitor_pageviews (first-party
 * pageview tracking, gated behind cookie consent — see
 * assets/js/visitor-tracker.js), never fabricated or padded with a fake
 * minimum. Matches on "slug=<slug>" within the tracked path rather than an
 * exact path string, since a course URL can carry extra query params (e.g.
 * an affiliate ?ref= token) on top of ?slug=.
 */
function get_live_viewer_count(string $courseSlug, int $windowMinutes = 5): int {
    $windowMinutes = max(1, min(30, $windowMinutes));
    $row = db_one(
        "SELECT COUNT(DISTINCT visitor_id) AS c FROM visitor_pageviews
         WHERE (path LIKE ? OR path LIKE ?) AND entered_at >= (NOW() - INTERVAL $windowMinutes MINUTE)",
        ['%slug=' . $courseSlug, '%slug=' . $courseSlug . '&%']
    );
    return (int) ($row['c'] ?? 0);
}

/** Top-rated published courses with at least one review — for a "Trending" spotlight row. */
function get_trending_courses(int $take = 3): array {
    return get_course_cards(
        '(SELECT COUNT(*) FROM reviews r WHERE r.course_id = c.id) > 0',
        [],
        'avg_rating DESC, review_count DESC, student_count DESC',
        $take
    );
}

/**
 * "Students Also Bought" — other published courses in the same category,
 * ranked the same way the browse grid's "Most Popular" is (POPULARITY_ORDER:
 * views first, purchases as the tiebreaker). $excludeUserId, when given,
 * drops any course that learner is already enrolled in — no point
 * suggesting something they already own.
 */
function get_related_courses(int $courseId, int $categoryId, ?int $excludeUserId = null, int $take = 3): array {
    $where = 'c.id != ? AND c.category_id = ?';
    $params = [$courseId, $categoryId];
    if ($excludeUserId !== null) {
        $where .= ' AND NOT EXISTS (SELECT 1 FROM enrollments e WHERE e.course_id = c.id AND e.user_id = ?)';
        $params[] = $excludeUserId;
    }
    return get_course_cards($where, $params, POPULARITY_ORDER, $take);
}

/**
 * Every creator's row (+ course/student counts, cheapest published-course
 * price) for school-card rendering — see includes/school_card.php.
 * $whereSql/$params filter the *creator* (u.*); course/category filtering
 * happens against the EXISTS subquery a caller adds to $whereSql, same
 * pattern get_course_cards() uses for courses.
 */
function get_school_cards(string $whereSql = '', array $params = [], string $orderBy = 'student_count DESC', ?int $limit = null): array {
    $sql = "
        SELECT u.id, u.name, u.school_name, u.avatar_url, u.school_cover_url, u.headline,
          u.pricing_model, u.school_monthly_price,
          (SELECT COUNT(*) FROM courses c WHERE c.creator_id = u.id AND c.status = 'PUBLISHED') AS course_count,
          (SELECT COUNT(*) FROM enrollments e JOIN courses c2 ON c2.id = e.course_id WHERE c2.creator_id = u.id AND c2.status = 'PUBLISHED') AS student_count,
          (SELECT MIN(c3.price) FROM courses c3 WHERE c3.creator_id = u.id AND c3.status = 'PUBLISHED' AND c3.price > 0) AS min_price,
          (SELECT COALESCE(AVG(r.rating), 0) FROM reviews r JOIN courses c4 ON c4.id = r.course_id WHERE c4.creator_id = u.id) AS avg_rating,
          (SELECT COALESCE(SUM(c7.view_count), 0) FROM courses c7 WHERE c7.creator_id = u.id AND c7.status = 'PUBLISHED') AS view_count,
          (SELECT COUNT(*) FROM school_follows sf WHERE sf.creator_id = u.id) AS follower_count,
          -- A school with no school_cover_url of its own borrows the
          -- thumbnail from its own most-recent published course instead of
          -- the card falling back to the plain placeholder box.
          (SELECT c6.thumbnail_url FROM courses c6 WHERE c6.creator_id = u.id AND c6.status = 'PUBLISHED' AND c6.thumbnail_url IS NOT NULL ORDER BY c6.created_at DESC LIMIT 1) AS fallback_thumbnail_url
        FROM users u
        WHERE u.role IN ('CREATOR', 'ADMIN')
          AND EXISTS (SELECT 1 FROM courses c5 WHERE c5.creator_id = u.id AND c5.status = 'PUBLISHED')
          " . ($whereSql ? "AND $whereSql" : '') . "
        ORDER BY $orderBy
    ";
    if ($limit) $sql .= " LIMIT $limit";
    return db_all($sql, $params);
}

function get_featured_schools(int $take = 6): array {
    return get_school_cards('', [], SCHOOL_POPULARITY_ORDER . ', u.created_at ASC', $take);
}

// Ranks a school by student_count (enrollments across its published
// courses — the "most bought" signal) first, then by view_count as the
// tiebreaker — the school with the most enrollments leads even if a
// less-enrolled school has more views, and among equally-enrolled schools
// the most-viewed one wins.
const SCHOOL_POPULARITY_ORDER = 'student_count DESC, view_count DESC';

/** Same fallback as get_school_cards()'s fallback_thumbnail_url, for a
 * single creator's own profile.php hero rather than a list of cards. */
function get_creator_fallback_thumbnail(int $creatorId): ?string {
    $row = db_one(
        "SELECT thumbnail_url FROM courses WHERE creator_id = ? AND status = 'PUBLISHED' AND thumbnail_url IS NOT NULL ORDER BY created_at DESC LIMIT 1",
        [$creatorId]
    );
    return $row['thumbnail_url'] ?? null;
}

const SCHOOL_SORT_OPTIONS = [
    'popular' => ['label' => 'Most Popular', 'order' => SCHOOL_POPULARITY_ORDER],
    'newest' => ['label' => 'Newest', 'order' => 'u.created_at DESC'],
    'rating' => ['label' => 'Highest Rated', 'order' => 'avg_rating DESC, student_count DESC'],
];

/** Schools with at least one reviewed course, highest-rated first — for a "Trending" spotlight row. */
function get_trending_schools(int $take = 3): array {
    return get_school_cards(
        "EXISTS (SELECT 1 FROM courses ct JOIN reviews rt ON rt.course_id = ct.id WHERE ct.creator_id = u.id)",
        [],
        'avg_rating DESC, student_count DESC',
        $take
    );
}

/**
 * @param string $query matches a school's own name, school_name, or any of
 *   its published course titles.
 * @param string $categorySlug schools with at least one published course in
 *   this category.
 */
function search_schools(string $query = '', string $categorySlug = '', string $sort = 'popular'): array {
    $where = [];
    $params = [];
    if ($categorySlug) {
        $where[] = "EXISTS (SELECT 1 FROM courses cc JOIN categories cat ON cat.id = cc.category_id WHERE cc.creator_id = u.id AND cc.status = 'PUBLISHED' AND cat.slug = ?)";
        $params[] = $categorySlug;
    }
    if ($query) {
        $where[] = "(u.name LIKE ? OR u.school_name LIKE ? OR EXISTS (SELECT 1 FROM courses cq WHERE cq.creator_id = u.id AND cq.status = 'PUBLISHED' AND cq.title LIKE ?))";
        $params[] = "%$query%";
        $params[] = "%$query%";
        $params[] = "%$query%";
    }
    $orderBy = SCHOOL_SORT_OPTIONS[$sort]['order'] ?? SCHOOL_SORT_OPTIONS['popular']['order'];
    return get_school_cards(implode(' AND ', $where), $params, $orderBy);
}

function get_course_by_slug(string $slug): ?array {
    $course = db_one('
        SELECT c.*, cat.name AS category_name, cat.slug AS category_slug,
          u.id AS creator_user_id, u.name AS creator_name, u.avatar_url AS creator_avatar_url,
          u.headline AS creator_headline, u.bio AS creator_bio,
          u.facebook_url AS creator_facebook_url, u.instagram_url AS creator_instagram_url,
          u.youtube_url AS creator_youtube_url, u.tiktok_url AS creator_tiktok_url, u.linkedin_url AS creator_linkedin_url,
          u.school_name AS creator_school_name, u.pricing_model AS creator_pricing_model, u.school_monthly_price AS creator_school_monthly_price
        FROM courses c
        JOIN categories cat ON cat.id = c.category_id
        JOIN users u ON u.id = c.creator_id
        WHERE c.slug = ?
    ', [$slug]);
    if (!$course) return null;

    $creatorStats = db_one(
        "SELECT COUNT(DISTINCT c.id) AS course_count, COUNT(e.id) AS student_count
         FROM courses c LEFT JOIN enrollments e ON e.course_id = c.id
         WHERE c.creator_id = ? AND c.status = 'PUBLISHED'",
        [$course['creator_user_id']]
    );
    $course['creator_course_count'] = (int) $creatorStats['course_count'];
    $course['creator_student_count'] = (int) $creatorStats['student_count'];

    $course['modules'] = db_all('SELECT * FROM modules WHERE course_id = ? ORDER BY sort_order ASC', [$course['id']]);
    foreach ($course['modules'] as &$module) {
        $module['lessons'] = db_all('SELECT * FROM lessons WHERE module_id = ? ORDER BY sort_order ASC', [$module['id']]);
    }
    unset($module);

    $course['reviews'] = db_all('
        SELECT r.*, u.name AS author_name, u.avatar_url AS author_avatar_url
        FROM reviews r JOIN users u ON u.id = r.author_id
        WHERE r.course_id = ? ORDER BY r.created_at DESC
    ', [$course['id']]);

    $course['student_count'] = (int) db_one('SELECT COUNT(*) AS n FROM enrollments WHERE course_id = ?', [$course['id']])['n'];

    return $course;
}

/** Average rating + count across every course review — used for homepage trust signals. */
function get_platform_rating(): array {
    $row = db_one("SELECT COALESCE(AVG(rating), 0) AS avg_rating, COUNT(*) AS n FROM reviews");
    return ['avg' => (float) $row['avg_rating'], 'count' => (int) $row['n']];
}

function get_platform_stats(): array {
    return [
        'course_count' => (int) db_one("SELECT COUNT(*) AS n FROM courses WHERE status = 'PUBLISHED'")['n'],
        'learner_count' => (int) db_one("SELECT COUNT(*) AS n FROM users WHERE role = 'LEARNER'")['n'],
        'creator_count' => (int) db_one("SELECT COUNT(*) AS n FROM users WHERE role = 'CREATOR'")['n'],
        // "Paid" means actually approved/sent (see dashboard/admin/withdrawals.php,
        // the only place status flips to APPROVED) — not gross earnings sitting
        // unwithdrawn, which hasn't actually reached anyone yet.
        'paid_creators' => (float) (db_one("SELECT COALESCE(SUM(amount),0) AS n FROM withdrawal_requests WHERE status='APPROVED' AND payee_type='CREATOR'")['n'] ?? 0),
        'paid_affiliates' => (float) (db_one("SELECT COALESCE(SUM(amount),0) AS n FROM withdrawal_requests WHERE status='APPROVED' AND payee_type='AFFILIATE'")['n'] ?? 0),
    ];
}

/**
 * Daily platform fee (10% commission) revenue for the last $days days, zero-filled
 * for days with no sales, plus a running cumulative total — the series behind the
 * admin dashboard's revenue growth curve.
 */
function get_daily_revenue_series(int $days = 30): array {
    $rows = db_all(
        "SELECT DATE(created_at) AS d, SUM(platform_fee) AS fee
         FROM earnings
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
         GROUP BY DATE(created_at)",
        [$days - 1]
    );
    $byDate = [];
    foreach ($rows as $r) $byDate[$r['d']] = (float) $r['fee'];

    $series = [];
    $cumulative = 0.0;
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $fee = $byDate[$date] ?? 0.0;
        $cumulative += $fee;
        $series[] = ['date' => $date, 'fee' => $fee, 'cumulative' => $cumulative];
    }
    return $series;
}

/**
 * Daily gross revenue collected from learners (i.e. the full sale price,
 * before the platform's 10% cut) for the last $days days, zero-filled for
 * days with no sales, plus a running cumulative total — the series behind
 * the admin "Revenue" tab's collections chart.
 */
function get_daily_collections_series(int $days = 30): array {
    $rows = db_all(
        "SELECT DATE(created_at) AS d, SUM(gross_amount) AS collected
         FROM earnings
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
         GROUP BY DATE(created_at)",
        [$days - 1]
    );
    $byDate = [];
    foreach ($rows as $r) $byDate[$r['d']] = (float) $r['collected'];

    $series = [];
    $cumulative = 0.0;
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $collected = $byDate[$date] ?? 0.0;
        $cumulative += $collected;
        $series[] = ['date' => $date, 'collected' => $collected, 'cumulative' => $cumulative];
    }
    return $series;
}

/**
 * Same shape as get_daily_collections_series(), scoped to one creator's own
 * net earnings (after the platform's 10% fee) — the series behind the
 * creator dashboard's own revenue growth chart, so a creator sees what they
 * actually earned each day/date, not the platform-wide gross.
 */
function get_creator_daily_earnings_series(int $creatorId, int $days = 30): array {
    $rows = db_all(
        "SELECT DATE(created_at) AS d, SUM(amount) AS collected
         FROM earnings
         WHERE creator_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
         GROUP BY DATE(created_at)",
        [$creatorId, $days - 1]
    );
    $byDate = [];
    foreach ($rows as $r) $byDate[$r['d']] = (float) $r['collected'];

    $series = [];
    $cumulative = 0.0;
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $collected = $byDate[$date] ?? 0.0;
        $cumulative += $collected;
        $series[] = ['date' => $date, 'collected' => $collected, 'cumulative' => $cumulative];
    }
    return $series;
}

/**
 * Totals + individual sales for one calendar day — what the admin "Revenue"
 * tab shows when looking up a specific date's collections.
 */
function get_day_collection_summary(string $date): array {
    $totals = db_one(
        "SELECT COALESCE(SUM(gross_amount),0) AS collected, COALESCE(SUM(platform_fee),0) AS fee,
                COALESCE(SUM(amount),0) AS creator_net, COUNT(*) AS n
         FROM earnings WHERE DATE(created_at) = ?",
        [$date]
    );
    $sales = db_all(
        "SELECT e.gross_amount, e.platform_fee, e.amount, e.created_at, c.title AS course_title, u.name AS creator_name
         FROM earnings e
         JOIN courses c ON c.id = e.course_id
         JOIN users u ON u.id = e.creator_id
         WHERE DATE(e.created_at) = ?
         ORDER BY e.created_at DESC",
        [$date]
    );
    return [
        'collected' => (float) $totals['collected'],
        'fee' => (float) $totals['fee'],
        'creator_net' => (float) $totals['creator_net'],
        'count' => (int) $totals['n'],
        'sales' => $sales,
    ];
}


function get_published_testimonials(): array {
    return db_all("
        SELECT t.*, u.name AS author_name, u.avatar_url AS author_avatar_url, u.headline AS author_headline
        FROM testimonials t JOIN users u ON u.id = t.author_id
        WHERE t.status = 'PUBLISHED' ORDER BY t.reviewed_at DESC
    ");
}

/**
 * Toggles a logged-in learner's opt-in interest in a course they haven't
 * bought yet — this (never mere page views) is what lets the course's
 * creator see that learner's contact details, and only for this course.
 * @return bool the new state (true = now interested, false = removed)
 */
function toggle_course_interest(int $userId, int $courseId): bool {
    $existing = db_one('SELECT id FROM course_interest WHERE user_id = ? AND course_id = ?', [$userId, $courseId]);
    if ($existing) {
        db_run('DELETE FROM course_interest WHERE id = ?', [$existing['id']]);
        return false;
    }
    db_insert('INSERT INTO course_interest (user_id, course_id) VALUES (?, ?)', [$userId, $courseId]);
    return true;
}

function is_interested_in_course(int $userId, int $courseId): bool {
    return (bool) db_one('SELECT id FROM course_interest WHERE user_id = ? AND course_id = ?', [$userId, $courseId]);
}

/** Learners who opted in to be contacted about this specific course — name/email/phone, for the course's own creator only. */
function get_interested_learners(int $courseId): array {
    return db_all(
        'SELECT ci.created_at, u.id, u.name, u.email, u.phone
         FROM course_interest ci JOIN users u ON u.id = ci.user_id
         WHERE ci.course_id = ? ORDER BY ci.created_at DESC',
        [$courseId]
    );
}

/** Views → shares → enrollments funnel for one course, for the creator's own analytics — aggregate counts only, no visitor identities. */
function get_course_funnel(int $courseId): array {
    $views = (int) (db_one('SELECT view_count AS n FROM courses WHERE id = ?', [$courseId])['n'] ?? 0);
    $shares = (int) (db_one('SELECT COUNT(*) AS n FROM course_shares WHERE course_id = ?', [$courseId])['n'] ?? 0);
    $enrollments = (int) (db_one('SELECT COUNT(*) AS n FROM enrollments WHERE course_id = ?', [$courseId])['n'] ?? 0);
    $interested = (int) (db_one('SELECT COUNT(*) AS n FROM course_interest WHERE course_id = ?', [$courseId])['n'] ?? 0);
    $shareChannels = db_all(
        "SELECT channel, COUNT(*) AS n FROM course_shares WHERE course_id = ? GROUP BY channel ORDER BY n DESC",
        [$courseId]
    );
    return [
        'views' => $views,
        'shares' => $shares,
        'enrollments' => $enrollments,
        'interested' => $interested,
        'conversion_rate' => $views > 0 ? round($enrollments / $views * 100, 1) : 0.0,
        'share_channels' => $shareChannels,
    ];
}

/**
 * Real payment-failure breakdown for a paid course, last $days — surfaces
 * WHY buyers who tried to pay didn't complete (e.g. "insufficient funds" vs
 * a genuine checkout error), so a creator can see this on their own
 * dashboard (dashboard/creator/course-manage.php) instead of it only being
 * visible to a platform admin running SQL by hand. Scoped to COURSE_PURCHASE
 * only, same as includes/payment_recovery.php's recovery-email queries.
 */
function get_course_payment_insight(int $courseId, int $days = 30): array {
    $days = max(1, min(90, $days));
    $attempted = (int) (db_one(
        "SELECT COUNT(*) AS n FROM payments WHERE course_id = ? AND type = 'COURSE_PURCHASE' AND created_at >= (NOW() - INTERVAL $days DAY)",
        [$courseId]
    )['n'] ?? 0);
    $failed = (int) (db_one(
        "SELECT COUNT(*) AS n FROM payments WHERE course_id = ? AND type = 'COURSE_PURCHASE' AND status = 'FAILED' AND created_at >= (NOW() - INTERVAL $days DAY)",
        [$courseId]
    )['n'] ?? 0);
    $insufficientFunds = (int) (db_one(
        "SELECT COUNT(*) AS n FROM payments WHERE course_id = ? AND type = 'COURSE_PURCHASE' AND status = 'FAILED' AND status_message LIKE '%insufficient%' AND created_at >= (NOW() - INTERVAL $days DAY)",
        [$courseId]
    )['n'] ?? 0);

    return [
        'attempted' => $attempted,
        'failed' => $failed,
        'insufficient_funds' => $insufficientFunds,
        'insufficient_funds_pct' => $failed > 0 ? (int) round($insufficientFunds / $failed * 100) : 0,
    ];
}
