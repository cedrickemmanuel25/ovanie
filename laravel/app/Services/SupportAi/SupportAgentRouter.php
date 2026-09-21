<?php

namespace App\Services\SupportAi;

use App\Models\SupportAiAgent;

class SupportAgentRouter
{
    public function selectByRole(string $role): SupportAiAgent
    {
        $allowed = ['general', 'business', 'technical', 'logistics', 'escalation'];
        if (! in_array($role, $allowed, true)) {
            $role = 'general';
        }

        return SupportAiAgent::query()
            ->active()
            ->where('role_key', $role)
            ->first()
            ?? SupportAiAgent::query()->active()->where('is_default', true)->first()
            ?? SupportAiAgent::query()->active()->firstOrFail();
    }

    public function selectHandoffTarget(?string $target, SupportAiAgent $current): ?SupportAiAgent
    {
        $target = trim((string) $target);

        if ($target === '' || $target === 'none' || $target === $current->role_key || $target === 'human') {
            return null;
        }

        return $this->selectByRole($target);
    }
}
