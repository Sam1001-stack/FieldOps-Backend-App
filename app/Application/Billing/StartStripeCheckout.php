<?php

namespace App\Application\Billing;

use App\Domain\Identity\User;
use App\Domain\Organization\Organization;
use Laravel\Cashier\Cashier;
use RuntimeException;
use Stripe\Checkout\Session;

final class StartStripeCheckout
{
    public function handle(User $user, Organization $organization, string $plan): Session
    {
        $catalog = config("billing.plans.{$plan}");
        if (! is_array($catalog) || ($catalog['price_cents'] ?? 0) < 1) {
            throw new RuntimeException('Dieser Plan kann nicht abgerechnet werden.');
        }

        if (! config('cashier.secret')) {
            throw new RuntimeException('Stripe ist nicht konfiguriert.');
        }

        $user->createOrGetStripeCustomer([
            'name' => $user->name,
            'email' => $user->email,
            'metadata' => [
                'organization_id' => (string) $organization->id,
            ],
        ]);

        $frontend = rtrim((string) config('billing.frontend_url'), '/');

        return Cashier::stripe()->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $user->stripe_id,
            'success_url' => $frontend.'/billing?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $frontend.'/billing?canceled=1',
            'client_reference_id' => (string) $organization->id,
            'line_items' => [[
                'price_data' => [
                    'currency' => config('cashier.currency', 'eur'),
                    'product_data' => [
                        'name' => 'FieldOps '.$catalog['name'],
                        'description' => $catalog['users'].' Nutzer · '.$catalog['jobs'].' Einsätze/Monat',
                    ],
                    'unit_amount' => $catalog['price_cents'],
                    'recurring' => ['interval' => $catalog['interval'] ?? 'month'],
                ],
                'quantity' => 1,
            ]],
            'metadata' => [
                'organization_id' => (string) $organization->id,
                'plan' => $plan,
            ],
            'subscription_data' => [
                'metadata' => [
                    'organization_id' => (string) $organization->id,
                    'plan' => $plan,
                ],
            ],
        ]);
    }
}
