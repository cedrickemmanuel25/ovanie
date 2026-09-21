<?php

namespace App\Services\SupportAi\Contracts;

use App\Models\SupportCall;

interface SupportTelephonyProvider
{
    /** @return array<string,mixed> */
    public function initiateOutbound(SupportCall $call, array $payload = []): array;

    /** @return array<string,mixed> */
    public function transfer(SupportCall $call, string $destination, array $payload = []): array;

    public function hangup(SupportCall $call): void;
}
