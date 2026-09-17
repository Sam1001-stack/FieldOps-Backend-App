<?php

namespace App\Application\Billing;

use App\Domain\Organization\Organization;

final class ApplyPaidPlan
{
    public function handle(?string $organizationId, ?string $plan): bool
    {
        if (! $organizationId || ! in_array($plan, ['starter', 'team'], true)) {
            return false;
        }

        $org = Organization::query()->find($organizationId);
        if (! $org) {
            return false;
        }

        $org->update(['plan' => $plan]);

        return true;
    }
}
