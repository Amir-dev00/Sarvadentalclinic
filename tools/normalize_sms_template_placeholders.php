<?php

declare(strict_types=1);

/**
 * One-time helper: convert known legacy {variable} tokens in sms_templates to #variable#.
 *
 * Dry-run (default):
 *   php tools/normalize_sms_template_placeholders.php
 *
 * Apply:
 *   php tools/normalize_sms_template_placeholders.php --apply
 *
 * Only recognized SmsTemplateRenderer keys are rewritten. Unrelated braces are left alone.
 * A JSON backup of changed rows is written under storage/backups/ before apply.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Sarva\Services\SmsTemplateRenderer;

$apply = in_array('--apply', $argv ?? [], true);
$root = dirname(__DIR__);

$envFile = $root . '/.env';
if (is_file($envFile)) {
    $dotenv = Dotenv\Dotenv::createImmutable($root);
    $dotenv->safeLoad();
}

$host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1';
$name = $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: '';
$user = $_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: '';
$pass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '';
$charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

if ($name === '') {
    fwrite(STDERR, "DB_DATABASE not configured.\n");
    exit(1);
}

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$name};charset={$charset}",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (Throwable $e) {
    fwrite(STDERR, 'DB connection failed: ' . $e->getMessage() . "\n");
    exit(1);
}

$rows = $pdo->query('SELECT id, slug, name, body FROM sms_templates WHERE deleted_at IS NULL')->fetchAll();
$changes = [];

foreach ($rows as $row) {
    $before = (string) $row['body'];
    $after = SmsTemplateRenderer::normalizeToCanonical($before);
    if ($before === $after) {
        continue;
    }
    $changes[] = [
        'id' => (int) $row['id'],
        'slug' => (string) $row['slug'],
        'name' => (string) $row['name'],
        'before' => $before,
        'after' => $after,
    ];
}

echo count($changes) . " template(s) contain legacy {variable} syntax.\n";
foreach ($changes as $c) {
    echo "- #{$c['id']} {$c['slug']}\n";
}

if ($changes === []) {
    exit(0);
}

if (!$apply) {
    echo "Dry-run only. Re-run with --apply to update rows (backup will be written).\n";
    exit(0);
}

$backupDir = $root . '/storage/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}
$backupFile = $backupDir . '/sms_templates_placeholder_normalize_' . date('Ymd_His') . '.json';
file_put_contents($backupFile, json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "Backup: {$backupFile}\n";

$upd = $pdo->prepare('UPDATE sms_templates SET body=? WHERE id=?');
foreach ($changes as $c) {
    $upd->execute([$c['after'], $c['id']]);
}
echo 'Updated ' . count($changes) . " row(s).\n";
