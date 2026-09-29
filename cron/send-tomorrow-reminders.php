<?php

declare(strict_types=1);

/**
 * LEGACY compatibility entry point. Do not schedule this file in cPanel.
 *
 * Production cron is only cron/process-sms-automation.php every 5 minutes.
 * This file delegates to that same script so it cannot grow a second
 * reminder query or a second send_time check.
 */

require __DIR__ . '/process-sms-automation.php';
