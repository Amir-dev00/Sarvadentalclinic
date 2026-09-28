<?php

declare(strict_types=1);

namespace Sarva\Payment;

use Sarva\Interfaces\PaymentGatewayInterface;

/**
 * Development-only sandbox gateway. Never use as silent production.
 */
final class SandboxPaymentGateway implements PaymentGatewayInterface
{
    public function createPayment(int $amount, string $description, array $meta = []): array
    {
        $authority = 'SANDBOX-' . bin2hex(random_bytes(8));
        $callback = $_ENV['PAYMENT_CALLBACK_URL'] ?? (($_ENV['APP_URL'] ?? '') . '/payment/callback');
        return [
            'ok' => true,
            'authority' => $authority,
            'redirect_url' => rtrim((string) ($_ENV['APP_URL'] ?? ''), '/') . '/payment/sandbox?authority=' . urlencode($authority),
            'raw' => ['amount' => $amount, 'description' => $description, 'meta' => $meta, 'callback' => $callback],
        ];
    }

    public function verifyPayment(string $authority, int $amount): array
    {
        if (!str_starts_with($authority, 'SANDBOX-')) {
            return ['ok' => false, 'message' => 'شناسه پرداخت نامعتبر است.'];
        }
        return [
            'ok' => true,
            'ref_id' => 'REF-' . substr(hash('sha256', $authority . $amount), 0, 12),
            'status' => 'paid',
            'raw' => ['authority' => $authority, 'amount' => $amount],
        ];
    }

    public function getTransactionStatus(string $authority): array
    {
        return ['ok' => true, 'status' => 'unknown', 'authority' => $authority];
    }
}
