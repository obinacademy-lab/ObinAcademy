<?php
/**
 * Admin notifications — write side only for now (Phase 2). The read/mark-read
 * side and the topbar bell UI land in a later phase; the table already
 * exists (schema.sql) so writes can start immediately without a schema
 * change later.
 */

function create_admin_notification(string $type, string $message, ?int $relatedLeadId = null): void {
    db_insert(
        'INSERT INTO admin_notifications (type, message, related_lead_id) VALUES (?, ?, ?)',
        [$type, substr($message, 0, 500), $relatedLeadId]
    );
}
