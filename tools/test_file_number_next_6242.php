<?php

declare(strict_types=1);

/**
 * File-number sequence: active baseline + free soft-deleted blockers.
 * Run: php tools/test_file_number_next_6242.php
 */

$root = dirname(__DIR__);
$src = file_get_contents($root . '/src/Services/PatientFileNumberService.php') ?: '';

$failed = 0;
$passed = 0;

function assert_true(string $label, bool $cond): void
{
    global $failed, $passed;
    if ($cond) {
        $passed++;
        echo "PASS  {$label}\n";
        return;
    }
    $failed++;
    echo "FAIL  {$label}\n";
}

assert_true('next available is a distinct method', str_contains($src, 'function nextAvailableFileNumber'));
assert_true('suggestNext uses nextAvailable (not last+1 twice)', str_contains($src, 'return $this->nextAvailableFileNumber();'));
assert_true('active baseline excludes deleted_at for max', str_contains($src, 'AND deleted_at IS NULL'));
assert_true('soft-deleted blockers are released with ARCH- prefix', str_contains($src, "CONCAT('ARCH-', id, '-', ?)"));
assert_true('allocator still uses GET_LOCK', str_contains($src, 'GET_LOCK'));
assert_true('no hardcoded 6242 return', !preg_match('/return\s+[\'"]6242[\'"]/', $src));
assert_true('inspect SQL exists', is_file($root . '/database/inspect_file_number_next_6242.sql'));

$inspect = file_get_contents($root . '/database/inspect_file_number_next_6242.sql') ?: '';
assert_true('inspect SQL is diagnostic-first', str_contains($inspect, 'READ-ONLY') || str_contains($inspect, 'Inspect'));
assert_true('optional update is commented', str_contains($inspect, '-- UPDATE patients'));

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
