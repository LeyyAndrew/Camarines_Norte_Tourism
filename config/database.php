<?php

if (file_exists(__DIR__ . '/../.env')) {
    foreach (file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        putenv(trim($line));
    }
}

$url = getenv('DATABASE_URL');

if (!$url) {
    die("Connection Failed: DATABASE_URL is not set");
}

$u = parse_url($url);
parse_str($u['query'] ?? '', $q);

$isLocal = in_array($u['host'], ['localhost', '127.0.0.1']);

$dsn = sprintf(
    "pgsql:host=%s;port=%d;dbname=%s",
    $u['host'],
    $u['port'] ?? 5432,
    ltrim($u['path'], '/')
);

if (!$isLocal) {
    $dsn .= ";sslmode=" . ($q['sslmode'] ?? 'require');
    $dsn .= ";options=endpoint=" . explode('.', $u['host'])[0];
}

try {
    $pdo = new PDO($dsn, $u['user'], $u['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection Failed: " . $e->getMessage());
}