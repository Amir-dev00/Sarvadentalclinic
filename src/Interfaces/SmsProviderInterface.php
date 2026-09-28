<?php

declare(strict_types=1);

namespace Sarva\Interfaces;

interface SmsProviderInterface
{
    public function sendMessage(string $mobile, string $message): array;

    public function sendOtp(string $mobile, string $code): array;

    public function sendAppointmentReminder(string $mobile, string $message): array;
}
