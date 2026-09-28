<?php

declare(strict_types=1);

/**
 * Root front-controller for hosts that point document root at project root.
 * Prefer pointing the vhost/docroot to /public when possible.
 */

require __DIR__ . '/public/index.php';
