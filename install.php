<?php
declare(strict_types=1);

/**
 * One-click installer for local XAMPP.
 * Open: http://localhost/Visitor/install.php
 * Delete this file after a successful install.
 */

$config = require __DIR__ . '/config/config.php';
$messages = [];
$ok = false;

try {
    $mysqli = new mysqli($config['DB_HOST'], $config['DB_USER'], $config['DB_PASS'], '', (int) $config['DB_PORT']);
    if ($mysqli->connect_error) {
        throw new RuntimeException($mysqli->connect_error);
    }
    $mysqli->set_charset($config['DB_CHARSET']);

    $sql = file_get_contents(__DIR__ . '/sql/schema.sql');
    if ($sql === false) {
        throw new RuntimeException('Could not read sql/schema.sql');
    }

    if (!$mysqli->multi_query($sql)) {
        throw new RuntimeException($mysqli->error);
    }
    do {
        if ($result = $mysqli->store_result()) {
            $result->free();
        }
    } while ($mysqli->more_results() && $mysqli->next_result());

    if ($mysqli->errno) {
        throw new RuntimeException($mysqli->error);
    }

    $hash = password_hash('password', PASSWORD_DEFAULT);
    $db = $mysqli->real_escape_string($config['DB_NAME']);
    $mysqli->select_db($config['DB_NAME']);
    $stmt = $mysqli->prepare('UPDATE users SET password_hash = ?');
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    $stmt->close();
    $mysqli->close();

    $messages[] = 'Database created and seeded successfully.';
    $messages[] = 'Login with username: admin / password: password';
    $ok = true;
} catch (Throwable $e) {
    $messages[] = 'Install failed: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install — Visitor.</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600&family=Syne:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="auth-body">
<div class="public-main">
    <div class="public-card">
        <h1>Install</h1>
        <p>Visitor Management System setup</p>
        <hr>
        <?php foreach ($messages as $m): ?>
            <p style="color:<?= $ok ? '#2f6b3c' : '#8a3d3d' ?>"><?= htmlspecialchars($m) ?></p>
        <?php endforeach; ?>
        <?php if ($ok): ?>
            <p><a class="btn-auth" style="display:inline-block;width:auto;padding:.8rem 1.4rem;" href="login.php">Go to Sign In</a></p>
            <p style="font-size:.85rem;color:#7a756b;margin-top:1rem;">Delete <code>install.php</code> after setup.</p>
        <?php else: ?>
            <p style="font-size:.85rem;color:#7a756b;">Ensure MySQL is running in XAMPP and credentials in <code>config/config.php</code> are correct.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
