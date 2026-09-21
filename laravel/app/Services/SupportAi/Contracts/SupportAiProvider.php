<?php

namespace App\Services\SupportAi\Contracts;

use App\Models\SupportAiAgent;
use App\Models\SupportConversation;

interface SupportAiProvider
{
    /**
     * @return array{body:string,confidence:float,provider:string,metadata?:array}
     */
    public function generate(
        SupportConversation $conversation,
        SupportAiAgent $agent,
        string $message,
        array $context = []
    ): array;
}
