<?php

namespace App\Application\Billing;

use Laravel\Cashier\Cashier;
use RuntimeException;

final class SyncStripeCheckout
{
    public function __construct(private ApplyPaidPlan $applyPaidPlan) {}

    public function handle(string $sessionId): bool
    {
        if (! config('cashier.secret')) {
            throw new RuntimeException('Stripe ist nicht konfiguriert.');
        }

        $session = Cashier::stripe()->checkout->sessions->retrieve($sessionId);

        $paid = in_array($session->payment_status, ['paid', 'no_payment_required'], true)
            || $session->status === 'complete';

        if (! $paid) {
            return false;
        }

        $organizationId = $session->metadata['organization_id'] ?? $session->client_reference_id;
        $plan = $session->metadata['plan'] ?? null;

        return $this->applyPaidPlan->handle(
            $organizationId ? (string) $organizationId : null,
            $plan ? (string) $plan : null,
        );
    }
}
