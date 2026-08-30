<?php

function get_categories(): array {
    return db_all('SELECT * FROM categories ORDER BY name ASC');
}

/** Course rows (+ category name, creator name/avatar, student count, avg rating) for card rendering. */
function get_course_cards(string $whereSql = '', array $params = [], string $orderBy = 'c.created_at DESC', ?int $limit = null): array {
    $sql = "
        SELECT c.*, cat.name AS category_name, u.name AS creator_name, u.avatar_url AS creator_avatar_url,
          (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS student_count,
          (SELECT COALESCE(AVG(r.rating), 0) FROM reviews r WHERE r.course_id = c.id) AS avg_rating,
          (SELECT COUNT(*) FROM reviews r WHERE r.course_id = c.id) AS review_count
        FROM courses c
        JOIN categories cat ON cat.id = c.category_id
        JOIN users u ON u.id = c.creator_id
        WHERE c.status = 'PUBLISHED' " . ($whereSql ? "AND $whereSql" : '') . "
        ORDER BY $orderBy
    ";
    if ($limit) $sql .= " LIMIT $limit";
    return db_all($sql, $params);
}

function get_featured_courses(int $take = 6): array {
    return get_course_cards('', [], 'c.created_at DESC', $take);
}

const COURSE_SORT_OPTIONS = [
    'newest' => ['label' => 'Newest', 'order' => 'c.created_at DESC'],
    'popular' => ['label' => 'Most Popular', 'order' => 'student_count DESC, c.created_at DESC'],
    'rating' => ['label' => 'Highest Rated', 'order' => 'avg_rating DESC, review_count DESC'],
    'price_low' => ['label' => 'Price: Low to High', 'order' => 'c.price ASC'],
    'price_high' => ['label' => 'Price: High to Low', 'order' => 'c.price DESC'],
];

function search_courses(string $query = '', string $categorySlug = '', string $sort = 'newest'): array {
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
    $orderBy = COURSE_SORT_OPTIONS[$sort]['order'] ?? COURSE_SORT_OPTIONS['newest']['order'];
    return get_course_cards(implode(' AND ', $where), $params, $orderBy);
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

function get_course_by_slug(string $slug): ?array {
    $course = db_one('
        SELECT c.*, cat.name AS category_name, cat.slug AS category_slug,
          u.id AS creator_user_id, u.name AS creator_name, u.avatar_url AS creator_avatar_url,
          u.headline AS creator_headline, u.bio AS creator_bio,
          u.facebook_url AS creator_facebook_url, u.instagram_url AS creator_instagram_url,
          u.youtube_url AS creator_youtube_url, u.tiktok_url AS creator_tiktok_url, u.linkedin_url AS creator_linkedin_url
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

/** Every creator with at least one published course — the "Communities" directory. */
function get_active_creators(): array {
    return db_all(
        "SELECT u.*,
            (SELECT COUNT(*) FROM courses c WHERE c.creator_id = u.id AND c.status = 'PUBLISHED') AS course_count,
            (SELECT COUNT(*) FROM enrollments e JOIN courses c ON c.id = e.course_id WHERE c.creator_id = u.id AND c.status = 'PUBLISHED') AS student_count,
            (SELECT COALESCE(AVG(r.rating), 0) FROM reviews r JOIN courses c ON c.id = r.course_id WHERE c.creator_id = u.id AND c.status = 'PUBLISHED') AS avg_rating,
            (SELECT COUNT(*) FROM reviews r JOIN courses c ON c.id = r.course_id WHERE c.creator_id = u.id AND c.status = 'PUBLISHED') AS review_count
         FROM users u
         WHERE u.role = 'CREATOR'
           AND (SELECT COUNT(*) FROM courses c WHERE c.creator_id = u.id AND c.status = 'PUBLISHED') > 0
         ORDER BY student_count DESC, u.name ASC"
    );
}

/** One creator's public "community" profile — null if they don't have a published course to show for it. */
function get_creator_profile(int $creatorId): ?array {
    $creator = db_one("SELECT * FROM users WHERE id = ? AND role = 'CREATOR'", [$creatorId]);
    if (!$creator) return null;

    $stats = db_one(
        "SELECT
            (SELECT COUNT(*) FROM courses c WHERE c.creator_id = ? AND c.status = 'PUBLISHED') AS course_count,
            (SELECT COUNT(*) FROM enrollments e JOIN courses c ON c.id = e.course_id WHERE c.creator_id = ? AND c.status = 'PUBLISHED') AS student_count,
            (SELECT COALESCE(AVG(r.rating), 0) FROM reviews r JOIN courses c ON c.id = r.course_id WHERE c.creator_id = ? AND c.status = 'PUBLISHED') AS avg_rating,
            (SELECT COUNT(*) FROM reviews r JOIN courses c ON c.id = r.course_id WHERE c.creator_id = ? AND c.status = 'PUBLISHED') AS review_count",
        [$creatorId, $creatorId, $creatorId, $creatorId]
    );
    if ((int) $stats['course_count'] === 0) return null;

    $creator['course_count'] = (int) $stats['course_count'];
    $creator['student_count'] = (int) $stats['student_count'];
    $creator['avg_rating'] = (float) $stats['avg_rating'];
    $creator['review_count'] = (int) $stats['review_count'];
    return $creator;
}

function get_published_testimonials(): array {
    return db_all("
        SELECT t.*, u.name AS author_name, u.avatar_url AS author_avatar_url, u.headline AS author_headline
        FROM testimonials t JOIN users u ON u.id = t.author_id
        WHERE t.status = 'PUBLISHED' ORDER BY t.reviewed_at DESC
    ");
}
