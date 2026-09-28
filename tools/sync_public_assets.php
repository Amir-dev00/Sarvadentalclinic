<?php

declare(strict_types=1);

/**
 * Mirror project /assets into /public/assets (uploads + any missing files).
 * Safe to re-run. Usage: php tools/sync_public_assets.php
 */

$root = dirname(__DIR__);
$srcRoot = $root . DIRECTORY_SEPARATOR . 'assets';
$dstRoot = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets';

if (!is_dir($srcRoot)) {
    fwrite(STDERR, "Source assets directory missing: {$srcRoot}\n");
    exit(1);
}
if (!is_dir($dstRoot) && !mkdir($dstRoot, 0755, true) && !is_dir($dstRoot)) {
    fwrite(STDERR, "Cannot create destination: {$dstRoot}\n");
    exit(1);
}

$copied = 0;
$skipped = 0;
$errors = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($srcRoot, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    /** @var SplFileInfo $file */
    if (!$file->isFile()) {
        continue;
    }
    $relative = substr($file->getPathname(), strlen($srcRoot) + 1);
    $dest = $dstRoot . DIRECTORY_SEPARATOR . $relative;
    $destDir = dirname($dest);
    if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
        fwrite(STDERR, "mkdir failed: {$destDir}\n");
        $errors++;
        continue;
    }
    if (is_file($dest) && filesize($dest) === filesize($file->getPathname())) {
        $skipped++;
        continue;
    }
    if (@copy($file->getPathname(), $dest)) {
        @chmod($dest, 0644);
        $copied++;
        echo "copied: {$relative}\n";
    } else {
        fwrite(STDERR, "copy failed: {$relative}\n");
        $errors++;
    }
}

echo "Done. copied={$copied} skipped={$skipped} errors={$errors}\n";
exit($errors > 0 ? 1 : 0);
