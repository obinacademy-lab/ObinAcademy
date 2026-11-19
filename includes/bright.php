<?php
/**
 * Bright: the icon sprite and small helpers shared by the redesigned public pages (home, campuses, campus, about,
 * teach, contact). Styles: assets/css/bright.css (everything is prefixed b- and scoped under .br).
 */

/** Icon paths, keyed by name. br_sprite() prints them once per page; br_ic() references them. */
const BR_ICONS = [
    'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
    'cap' => '<path d="m2 9 10-5 10 5-10 5z"/><path d="M6 11v5c0 1.5 2.7 3 6 3s6-1.5 6-3v-5"/>',
    'home' => '<path d="M3 11 12 3l9 8v9a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z"/>',
    'book' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5z"/><path d="M4 19.5V21h16"/>',
    'live' => '<circle cx="12" cy="12" r="2"/><path d="M16.2 7.8a6 6 0 0 1 0 8.4M7.8 16.2a6 6 0 0 1 0-8.4M19 5a10 10 0 0 1 0 14M5 19A10 10 0 0 1 5 5"/>',
    'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6M16 4.8a3.5 3.5 0 0 1 0 6.4M18 14.3c2.1.7 3.5 2.6 3.5 5.7"/>',
    'gear' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>',
    'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/>',
    'search' => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
    'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
    'check' => '<path d="M20 6 9 17l-5-5"/>',
    'x' => '<path d="M18 6 6 18M6 6l12 12"/>',
    'play' => '<path d="M7 5v14l11-7z"/>',
    'award' => '<circle cx="12" cy="9" r="6"/><path d="m8.5 14-1.5 8 5-3 5 3-1.5-8"/>',
    'share' => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/>',
    'phone' => '<rect x="6" y="2" width="12" height="20" rx="3"/><path d="M11 18h2"/>',
    'pause' => '<circle cx="12" cy="12" r="10"/><path d="M10 9v6M14 9v6"/>',
    'chat' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/>',
    'video' => '<rect x="3" y="5" width="13" height="14" rx="3"/><path d="m16 10 5-3v10l-5-3"/>',
    'lock' => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
    'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
    'wa' => '<path d="M3 21l1.6-4.7A8.5 8.5 0 1 1 8 19.6z"/><path d="M9 9.5c.3 2 2.3 4.3 4.6 4.9l1.2-1.2 1.9.9-.4 1.6c-3.7.4-8-3.9-7.6-7.6l1.6-.4.9 1.9z"/>',
    'call' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/>',
    'chart' => '<path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 6-7"/>',
    'wallet' => '<rect x="2" y="6" width="20" height="14" rx="2"/><path d="M2 10h20M16 15h2"/>',
    'globe' => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15 15 0 0 1 0 20M12 2a15 15 0 0 0 0 20"/>',
    'down' => '<path d="m6 9 6 6 6-6"/>',
];

/** The icon sprite. Print it once near the top of a page; br_ic() then references the symbols by name. */
function br_sprite(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    echo '<svg width="0" height="0" style="position:absolute" aria-hidden="true">';
    foreach (BR_ICONS as $name => $paths) echo '<symbol id="br-i-' . $name . '" viewBox="0 0 24 24">' . $paths . '</symbol>';
    echo '</svg>';
}

function br_ic(string $name): string {
    return '<svg class="i" aria-hidden="true"><use href="#br-i-' . $name . '"/></svg>';
}

/**
 * One campus card (home and campuses page). Open campuses link to their page with the live monthly price (and the day
 * pass when it is on); campuses that are not open yet read "Opening soon".
 */
function br_campus_card(array $c, bool $hasDay): string {
    $open = $c['status'] === 'PUBLISHED';
    $photo = campus_photo($c);
    $day = $hasDay ? (float) ($c['daily_price'] ?? 0) : 0.0;
    $tag = $open ? 'a' : 'div';
    $href = $open ? ' href="' . e(base_url('campus.php?slug=' . rawurlencode($c['slug']))) . '"' : '';
    $im = $photo ? '<img src="' . e($photo) . '" alt="" loading="lazy">' : campus_icon($c['icon'], 1.6);
    if ($open) {
        $pr = '<div class="b-pr"><div><b>' . e(format_money((float) $c['monthly_price'])) . ' <small>/ month</small></b>'
            . ($day > 0 ? '<span>Day pass ' . e(format_money($day)) . '</span>' : '') . '</div><span class="b-go">View campus ' . br_ic('arrow') . '</span></div>';
    } else {
        $pr = '<div class="b-pr"><div><b style="font-family:var(--sans)">Opening soon</b></div></div>';
    }
    return '<' . $tag . ' class="b-card' . ($open ? '' : ' soon') . '" data-cat="' . e(pl_campus_category($c)) . '" data-open="' . ($open ? '1' : '0') . '"' . $href . '>'
        . '<div class="b-im">' . $im . '<span class="b-tag">' . ($open ? 'Open now' : 'Opening soon') . '</span></div>'
        . '<div class="b-bd"><div class="b-tx"><h3>' . e($c['name']) . '</h3><p>' . e($c['tagline']) . '</p></div>' . $pr . '</div></' . $tag . '>';
}
