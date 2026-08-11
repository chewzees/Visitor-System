<?php
declare(strict_types=1);

function i18n_boot(array $config): void
{
    if (isset($_GET['lang']) && in_array($_GET['lang'], $config['SUPPORTED_LANGS'], true)) {
        $_SESSION['lang'] = $_GET['lang'];
    }
    if (empty($_SESSION['lang'])) {
        $_SESSION['lang'] = $config['DEFAULT_LANG'];
    }
}

function lang(): string
{
    return $_SESSION['lang'] ?? 'en';
}

function __(string $key, ?string $fallback = null): string
{
    static $dict = null;
    if ($dict === null) {
        $file = __DIR__ . '/../lang/' . lang() . '.php';
        $dict = is_file($file) ? require $file : [];
    }
    return $dict[$key] ?? ($fallback ?? $key);
}
