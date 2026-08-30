<?php

function log_admin_action(int $actorId, string $actorName, string $action, string $targetType, string $targetLabel, ?string $detail = null): void {
    db_insert(
        'INSERT INTO audit_log (actor_id, actor_name, action, target_type, target_label, detail) VALUES (?, ?, ?, ?, ?, ?)',
        [$actorId, $actorName, $action, $targetType, $targetLabel, $detail]
    );
}
