<?php

declare(strict_types=1);

namespace Sarva\Payment;

use Sarva\Interfaces\PaymentGatewayInterface;

final class PaymentManager
{
    public static function make(): PaymentGatewayInterface
    {
        $driver = (string) ($_ENV['PAYMENT_DRIVER'] ?? 'sandbox');
        return match ($driver) {
            'sandbox' => new SandboxPaymentGateway(),
            default => new SandboxPaymentGateway(),
        };
    }
}
