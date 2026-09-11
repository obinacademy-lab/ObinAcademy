<?php
// A ticket has two ways in, same spirit as certificate.php's ?code= link:
//   ?token=...  — hash-verified, no login required (the link that gets
//                 emailed, and works for a guest or a printed/forwarded copy).
//   ?slug=...   — for a logged-in attendee revisiting from the site itself;
//                 looked up by session identity, no token needed or exposed.
require __DIR__ . '/includes/bootstrap.php';

$token = query_param('token');
$slug = query_param('slug');
$user = current_user();

$sql = "SELECT e.id AS enrollment_id, e.enrolled_at, e.user_id, e.guest_name, e.guest_email, e.ticket_tier,
          c.id AS course_id, c.title, c.slug, c.event_starts_at, c.event_ends_at, c.event_location, c.event_online_url,
          u.name AS attendee_account_name, creator.name AS creator_name
        FROM enrollments e
        JOIN courses c ON c.id = e.course_id
        LEFT JOIN users u ON u.id = e.user_id
        JOIN users creator ON creator.id = c.creator_id
        WHERE c.type = 'EVENT' ";

$ticket = null;
if ($token !== '') {
    $ticket = db_one($sql . 'AND e.access_token_hash = ?', [hash('sha256', $token)]);
} elseif ($user && $slug !== '') {
    $ticket = db_one($sql . 'AND e.user_id = ? AND c.slug = ?', [$user['id'], $slug]);
}

if (!$ticket) {
    http_response_code(404);
    $pageTitle = 'Ticket Not Found — Obin Academy';
    $noindex = true;
    require __DIR__ . '/includes/header.php';
    $message = ($slug !== '' && !$user)
        ? 'Log in to view this ticket, or use the link from your confirmation email.'
        : 'Check the link and try again, or use the link from your confirmation email.';
    echo '<div class="container" style="padding:80px 0; text-align:center;"><h1 class="h2">Ticket not found</h1><p class="muted" style="margin-top:10px;">' . e($message) . '</p></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$attendeeName = $ticket['attendee_account_name'] ?? $ticket['guest_name'] ?? 'Guest';
$isVip = $ticket['ticket_tier'] === 'VIP';
$reference = 'OA-EVT-' . str_pad((string) $ticket['enrollment_id'], 6, '0', STR_PAD_LEFT);
$eventUrl = base_url('courses/view.php?slug=' . $ticket['slug']);
$hasStarted = !empty($ticket['event_starts_at']) && strtotime($ticket['event_starts_at']) <= time();

$pageTitle = $ticket['title'] . ' — Ticket — Obin Academy';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e($pageTitle) ?></title>
  <link rel="icon" href="<?= e(versioned_asset('favicon.svg')) ?>" type="image/svg+xml">
  <link rel="icon" href="<?= e(versioned_asset('favicon-32x32.png')) ?>" type="image/png" sizes="32x32">
  <link rel="apple-touch-icon" href="<?= e(versioned_asset('apple-touch-icon.png')) ?>" sizes="180x180">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(versioned_asset('assets/css/style.css')) ?>">
  <style>
    body { background: var(--surface); min-height: 100vh; }
    .ticket-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; max-width: 640px; margin: 0 auto; padding: 20px 20px 0; }
    .ticket-wrap { max-width: 640px; margin: 24px auto 60px; padding: 0 20px; }
    .ticket-doc { background: #fff; border-radius: 20px; overflow: hidden; box-shadow: 0 30px 70px -30px rgba(20,24,27,0.35); }
    .ticket-head { background: linear-gradient(145deg, var(--brand-950), var(--brand-800)); color: #fff; padding: 28px 28px 22px; }
    .ticket-eyebrow { font-size: 11px; font-weight: 800; letter-spacing: 0.12em; text-transform: uppercase; color: var(--gold); }
    .ticket-title { margin-top: 8px; font-size: clamp(20px, 3vw, 26px); font-weight: 800; line-height: 1.25; }
    .ticket-organizer { margin-top: 8px; font-size: 13px; color: rgba(255,255,255,0.7); }
    .ticket-vip-badge { flex-shrink: 0; background: var(--gold); color: #1e1400; font-size: 11px; font-weight: 800; letter-spacing: 0.04em; padding: 5px 11px; border-radius: var(--radius-full); }
    .ticket-stub-divider { position: relative; height: 0; border-top: 2px dashed var(--border); }
    .ticket-stub-divider::before, .ticket-stub-divider::after {
      content: ""; position: absolute; top: -12px; width: 24px; height: 24px; border-radius: 50%; background: var(--surface);
    }
    .ticket-stub-divider::before { left: -32px; }
    .ticket-stub-divider::after { right: -32px; }
    .ticket-body { padding: 26px 28px 28px; }
    .ticket-row { display: flex; align-items: flex-start; gap: 14px; }
    .ticket-row + .ticket-row { margin-top: 18px; }
    .ticket-row-icon { width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0; background: var(--surface); color: var(--accent); display: flex; align-items: center; justify-content: center; }
    .ticket-row-icon svg { width: 16px; height: 16px; }
    .ticket-row-label { font-size: 10.5px; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; color: var(--muted); }
    .ticket-row-value { margin-top: 2px; font-size: 14.5px; font-weight: 600; }
    .ticket-row-value a { color: var(--accent); }
    .ticket-attendee { margin-top: 22px; padding-top: 22px; border-top: 1px solid var(--border); text-align: center; }
    .ticket-attendee-label { font-size: 10.5px; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; color: var(--muted); }
    .ticket-attendee-name { margin-top: 6px; font-size: 22px; font-weight: 800; }
    .ticket-ref { margin-top: 18px; text-align: center; font-size: 11.5px; color: var(--muted); font-variant-numeric: tabular-nums; }
    @media print {
      @page { margin: 0.5in; }
      body { background: #fff; }
      .ticket-toolbar { display: none !important; }
      .ticket-wrap { margin: 0; padding: 0; max-width: none; }
      .ticket-doc { box-shadow: none; }
    }
  </style>
</head>
<body>
  <div class="ticket-toolbar">
    <?php render_logo(); ?>
    <div class="row gap-2">
      <a href="<?= e($eventUrl) ?>" class="btn btn-outline btn-sm">← Back to Event</a>
      <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">🖨 Print / Save</button>
    </div>
  </div>

  <div class="ticket-wrap">
    <div class="ticket-doc">
      <div class="ticket-head">
        <div class="row between" style="align-items:center;">
          <div class="ticket-eyebrow"><?= $hasStarted ? 'Your Ticket' : 'You\'re Going!' ?></div>
          <?php if ($isVip): ?><span class="ticket-vip-badge">🎟 VIP</span><?php endif; ?>
        </div>
        <div class="ticket-title"><?= e($ticket['title']) ?></div>
        <div class="ticket-organizer">Hosted by <?= e($ticket['creator_name']) ?></div>
      </div>
      <div class="ticket-stub-divider"></div>
      <div class="ticket-body">
        <div class="ticket-row">
          <span class="ticket-row-icon"><?php dash_icon('calendar'); ?></span>
          <div>
            <div class="ticket-row-label">When</div>
            <div class="ticket-row-value">
              <?= $ticket['event_starts_at'] ? e(format_date($ticket['event_starts_at'])) . ' at ' . e(date('g:i A', strtotime($ticket['event_starts_at']))) : 'Date to be announced' ?>
              <?php if ($ticket['event_ends_at']): ?> &ndash; <?= e(date('g:i A', strtotime($ticket['event_ends_at']))) ?><?php endif; ?>
            </div>
          </div>
        </div>
        <div class="ticket-row">
          <span class="ticket-row-icon"><?php dash_icon($ticket['event_online_url'] ? 'globe' : 'map-pin'); ?></span>
          <div>
            <div class="ticket-row-label"><?= $ticket['event_online_url'] ? 'Online' : 'Location' ?></div>
            <div class="ticket-row-value">
              <?php if ($ticket['event_online_url']): ?>
                <a href="<?= e($ticket['event_online_url']) ?>" target="_blank" rel="noopener noreferrer">Join the event &rarr;</a>
              <?php else: ?>
                <?= e($ticket['event_location'] ?: 'To be announced') ?>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="ticket-attendee">
          <div class="ticket-attendee-label">Attendee<?= $isVip ? ' · VIP Ticket' : ' · Ordinary Ticket' ?></div>
          <div class="ticket-attendee-name"><?= e($attendeeName) ?></div>
        </div>
        <div class="ticket-ref">Ticket Reference: <?= e($reference) ?></div>
      </div>
    </div>
  </div>
</body>
</html>
