<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();
logout_user($pdo);
flash_set('success', 'Signed out.');
redirect('login.php');
