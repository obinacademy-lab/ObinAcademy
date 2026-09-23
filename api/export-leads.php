<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/leads.php';
require_role(['ADMIN']);

$filters = ['q' => query_param('q'), 'status' => query_param('status'), 'type' => query_param('type'), 'source' => query_param('source')];
$leads = get_leads($filters, 1, 10000)['rows'];

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="obin-academy-leads-' . date('Y-m-d') . '.csv"');

// A lead's name/email/phone/source come straight from the public,
// unauthenticated capture-lead.php form — a cell starting with =+-@ is
// read as a formula by Excel/Sheets when this admin opens the file
// (classic CSV injection). Prefixing a leading apostrophe forces it back
// to plain text; leaves ordinary values untouched.
$csvSafe = fn($v) => is_string($v) && $v !== '' && str_contains('=+-@', $v[0]) ? "'" . $v : $v;

$out = fopen('php://output', 'w');
fputcsv($out, ['Name', 'Email', 'Phone', 'Type', 'Source', 'Status', 'Visits', 'First Visit', 'Last Visit', 'Consent', 'Unsubscribed', 'Created At']);
foreach ($leads as $l) {
    fputcsv($out, [
        $csvSafe($l['name']), $csvSafe($l['email']), $csvSafe($l['phone']), $l['lead_type'], $csvSafe($l['source']), $l['status'],
        $l['visit_count'], $l['first_visit_at'], $l['last_visit_at'],
        $l['consent_marketing'] ? 'Yes' : 'No', $l['unsubscribed'] ? 'Yes' : 'No', $l['created_at'],
    ]);
}
fclose($out);
