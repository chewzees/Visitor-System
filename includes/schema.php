<?php
declare(strict_types=1);

/**
 * Idempotent schema upgrades for existing installs.
 */
function ensure_app_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $columns = [
        'visitors' => [
            'photo_path' => "ALTER TABLE visitors ADD COLUMN photo_path VARCHAR(255) NULL AFTER qr_token",
            'host_email' => "ALTER TABLE visitors ADD COLUMN host_email VARCHAR(160) NULL AFTER host_name",
            'notes' => "ALTER TABLE visitors ADD COLUMN notes TEXT NULL AFTER purpose",
            'expected_out_at' => "ALTER TABLE visitors ADD COLUMN expected_out_at DATETIME NULL AFTER checked_out_at",
        ],
        'users' => [
            'reset_token' => "ALTER TABLE users ADD COLUMN reset_token VARCHAR(64) NULL AFTER last_login",
            'reset_expires' => "ALTER TABLE users ADD COLUMN reset_expires DATETIME NULL AFTER reset_token",
        ],
    ];

    foreach ($columns as $table => $defs) {
        foreach ($defs as $col => $sql) {
            if (!schema_has_column($pdo, $table, $col)) {
                try {
                    $pdo->exec($sql);
                } catch (Throwable $e) {
                    // ignore race / already exists
                }
            }
        }
    }

    // Optional notifications log table
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS notifications (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            visitor_id INT UNSIGNED NULL,
            channel VARCHAR(30) NOT NULL DEFAULT 'email',
            recipient VARCHAR(160) NOT NULL,
            subject VARCHAR(200) NOT NULL,
            body TEXT NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'sent',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_notif_visitor (visitor_id)
        ) ENGINE=InnoDB"
    );
}

function schema_has_column(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}
