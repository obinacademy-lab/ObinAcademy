<?php

function log_admin_action(int $actorId, string $actorName, string $action, string $targetType, string $targetLabel, ?string $detail = null): void {
    db_insert(
        'INSERT INTO audit_log (actor_id, actor_name, action, target_type, target_label, detail) VALUES (?, ?, ?, ?, ?, ?)',
        [$actorId, $actorName, $action, $targetType, $targetLabel, $detail]
    );
}

/**
 * Areas of the admin the log is grouped by (dashboard/admin/audit-log.php), keyed by the action prefix
 * ("campus.published" is in 'campus'). Anything else, such as the old course platform's
 * "course.approved" or "COURSE_PUBLISHED", falls into 'old'. Values: label, dashboard icon.
 */
const AUDIT_AREAS = [
    'campus' => ['Campuses', 'graduation-cap'],
    'instructor' => ['Teach', 'user-plus'],
    'payment' => ['Payments', 'wallet'],
    'lead' => ['Leads', 'tag'],
    'user' => ['Users', 'users'],
    'story' => ['Stories', 'quote'],
    'storage' => ['System', 'settings'],
    'old' => ['Old platform', 'clock'],
];

/** The AUDIT_AREAS key an action belongs to. */
function audit_area(string $action): string {
    $prefix = str_contains($action, '.') ? strstr($action, '.', true) : '';
    if ($prefix === 'payments') $prefix = 'payment';
    if ($prefix === 'audit') $prefix = 'storage'; // exports of this log count as System
    return $prefix !== '' && $prefix !== 'old' && isset(AUDIT_AREAS[$prefix]) ? $prefix : 'old';
}

/** SQL condition (with its params) matching one area, for filtering audit_log. */
function audit_area_sql(string $area): array {
    $known = array_diff(array_keys(AUDIT_AREAS), ['old']);
    if ($area === 'old') {
        $not = [];
        $params = [];
        foreach ($known as $k) { $not[] = 'action NOT LIKE ?'; $params[] = $k . '.%'; }
        foreach (['payments.%', 'audit.%'] as $p) { $not[] = 'action NOT LIKE ?'; $params[] = $p; }
        return ['(' . implode(' AND ', $not) . ')', $params];
    }
    if ($area === 'payment') return ['(action LIKE ? OR action LIKE ?)', ['payment.%', 'payments.%']];
    if ($area === 'storage') return ['(action LIKE ? OR action LIKE ?)', ['storage.%', 'audit.%']];
    return ['action LIKE ?', [$area . '.%']];
}

/** "published", "added live class", "approved creator application": the verb part of an action, readable. */
function audit_verb(string $action): string {
    $verb = str_contains($action, '.') ? substr(strrchr($action, '.'), 1) : $action;
    $words = array_values(array_filter(explode('_', strtolower($verb))));
    if (count($words) > 1 && str_ends_with(end($words), 'ed')) array_unshift($words, array_pop($words));
    return implode(' ', $words);
}

/** 'bad' for removals and permission changes, 'good' for things going live, 'watch' for data leaving, else ''. */
function audit_tone(string $action): string {
    $a = strtolower($action);
    foreach (['deleted', 'removed', 'rejected', 'role_changed', 'cleaned'] as $w) if (str_contains($a, $w)) return 'bad';
    if (str_contains($a, 'exported')) return 'watch';
    if (str_contains($a, 'unpublished')) return '';
    foreach (['published', 'approved', 'accepted', 'paid', 'added'] as $w) if (str_contains($a, $w)) return 'good';
    return '';
}
