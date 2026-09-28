<?php

declare(strict_types=1);

return [
    'driver' => $_ENV['PAYMENT_DRIVER'] ?? 'sandbox',
    'merchant_id' => $_ENV['PAYMENT_MERCHANT_ID'] ?? '',
    'callback_url' => $_ENV['PAYMENT_CALLBACK_URL'] ?? '',
    'currency' => $_ENV['PAYMENT_CURRENCY'] ?? 'IRT',
];
