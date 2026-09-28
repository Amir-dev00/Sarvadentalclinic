<?php

declare(strict_types=1);

namespace Sarva\Sms;

use Sarva\Interfaces\SmsProviderInterface;

final class SmsManager
{
    public static function make(): SmsProviderInterface
    {
        $driver = strtolower((string) (config('sms.driver') ?: ($_ENV['SMS_DRIVER'] ?? 'log')));
        return match ($driver) {
            'smsir', 'sms.ir' => new SmsIrProvider(),
            default => new LogSmsProvider(),
        };
    }

    public static function driver(): string
    {
        $driver = strtolower((string) (config('sms.driver') ?: ($_ENV['SMS_DRIVER'] ?? 'log')));
        return in_array($driver, ['smsir', 'sms.ir'], true) ? 'smsir' : 'log';
    }
}
