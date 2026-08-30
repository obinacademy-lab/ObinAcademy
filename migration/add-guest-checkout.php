<?php
// One-off: adds guest-checkout support to an existing database (user_id
// becomes nullable on enrollments/payments, plus guest_name/guest_email/
// access_token_hash columns). Safe to re-run — checks information_schema
// before altering anything.
//   php migration/add-guest-checkout.php

require __DIR__ . '/../includes/db.php';

$pdo = db();
$dbName = DB_NAME;

function columnExists(PDO $pdo, string $dbName, string $table, string $column): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$dbName, $table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function isNullable(PDO $pdo, string $dbName, string $table, string $column): bool {
    $stmt = $pdo->prepare('SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$dbName, $table, $column]);
    return $stmt->fetchColumn() === 'YES';
}

foreach (['enrollments', 'payments'] as $table) {
    if (!isNullable($pdo, $dbName, $table, 'user_id')) {
        echo "Altering $table.user_id to be nullable...\n";
        $pdo->exec("ALTER TABLE `$table` MODIFY user_id INT NULL");
    } else {
        echo "$table.user_id already nullable, skipping.\n";
    }

    foreach (['guest_name' => 'VARCHAR(191) NULL', 'guest_email' => 'VARCHAR(191) NULL', 'access_token_hash' => 'VARCHAR(64) NULL'] as $col => $def) {
        if (!columnExists($pdo, $dbName, $table, $col)) {
            echo "Adding $table.$col...\n";
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN $col $def");
        } else {
            echo "$table.$col already exists, skipping.\n";
        }
    }
}

// Unique index on access_token_hash so a hash can never collide across rows.
foreach (['enrollments', 'payments'] as $table) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = 'uniq_access_token_hash'");
    $stmt->execute([$dbName, $table]);
    if ((int) $stmt->fetchColumn() === 0) {
        echo "Adding unique index on $table.access_token_hash...\n";
        $pdo->exec("ALTER TABLE `$table` ADD UNIQUE KEY uniq_access_token_hash (access_token_hash)");
    } else {
        echo "$table.uniq_access_token_hash already exists, skipping.\n";
    }
}

echo "Done.\n";
