<?php

declare(strict_types=1);

namespace Sarva\Interfaces;

interface PaymentGatewayInterface
{
    /** @return array{ok:bool, authority?:string, redirect_url?:string, message?:string, raw?:mixed} */
    public function createPayment(int $amount, string $description, array $meta = []): array;

    /** @return array{ok:bool, ref_id?:string, status?:string, message?:string, raw?:mixed} */
    public function verifyPayment(string $authority, int $amount): array;

    public function getTransactionStatus(string $authority): array;
}
