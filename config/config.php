<?php
declare(strict_types=1);

/**
 * Application configuration
 * Adjust DB credentials for your XAMPP MySQL setup.
 */
return [
    'APP_NAME' => 'Visitor.',
    'APP_FULL_NAME' => 'Visitor Management System',
    'APP_URL' => 'http://localhost/Visitor', // overridden automatically when possible
    'APP_TIMEZONE' => 'Asia/Kuala_Lumpur',
    'APP_DEBUG' => true,
    'APP_SECRET' => 'change-me-to-a-long-random-secret-key',
    'SESSION_LIFETIME' => 3600,
    'DEFAULT_LANG' => 'en',
    'SUPPORTED_LANGS' => ['en', 'zh'],

    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_NAME' => 'visitor_mgmt',
    'DB_USER' => 'root',
    'DB_PASS' => '',
    'DB_CHARSET' => 'utf8mb4',

    'ENTRANCE_TOKEN' => 'entrance',

    // Hours after check-in before a guest is flagged overdue / still inside
    'OVERDUE_HOURS' => 8,

    // Mail (uses PHP mail() — configure sendmail/SMTP in php.ini for production)
    'MAIL_FROM' => 'noreply@visitor.local',
    'MAIL_FROM_NAME' => 'Visitor Management',
    'NOTIFY_HOST_ON_CHECKIN' => true,
    'NOTIFY_HOST_ON_REGISTER' => true,
];
