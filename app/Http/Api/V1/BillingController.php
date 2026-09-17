<?php

namespace App\Http\Api\V1;

use App\Application\Billing\StartStripeCheckout;
use App\Application\Billing\SyncStripeCheckout;
use Illuminate\Http\Request;
use RuntimeException;

class BillingController extends Controller
{
    public function show(Request $request)
    {
        $org = $request->user()->currentOrganization;
        abort_unless($org, 404);
        $this->authorize('view', $org);

        $plans = collect(config('billing.plans', []))->map(fn (array $plan, string $id) => [
            'id' => $id,
            ...$plan,
        ])->values();

        return [
            'configured' => filled(config('cashier.secret')),
            'current' => $org->plan,
            'limits' => $org->planLimits(),
            'plans' => $plans,
        ];
    }

    public function checkout(Request $request, StartStripeCheckout $start)
    {
        $org = $request->user()->currentOrganization;
        abort_unless($org, 404);
        $this->authorize('billing', $org);

        $data = $request->validate([
            'plan' => ['required', 'in:starter,team'],
        ]);

        try {
            $session = $start->handle($request->user(), $org, $data['plan']);
        } catch (RuntimeException $e) {
            abort(503, $e->getMessage());
        }

        return ['url' => $session->url];
    }

    public function sync(Request $request, SyncStripeCheckout $sync)
    {
        $org = $request->user()->currentOrganization;
        abort_unless($org, 404);
        $this->authorize('billing', $org);

        $data = $request->validate([
            'session_id' => ['required', 'string'],
        ]);

        try {
            $applied = $sync->handle($data['session_id']);
        } catch (RuntimeException $e) {
            abort(503, $e->getMessage());
        }

        $org->refresh();

        return [
            'applied' => $applied,
            'plan' => $org->plan,
        ];
    }
}
