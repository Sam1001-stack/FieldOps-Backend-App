<?php

namespace App\Providers;

use App\Application\Billing\ApplyPaidPlan;
use App\Domain\Catalog\CatalogItem;
use App\Domain\CRM\Customer;
use App\Domain\Invoicing\Invoice;
use App\Domain\Jobs\ServiceJob;
use App\Domain\Organization\Organization;
use App\Policies\CatalogItemPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\ServiceJobPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookReceived;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        JsonResource::withoutWrapping();

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Gate::policy(ServiceJob::class, ServiceJobPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(CatalogItem::class, CatalogItemPolicy::class);
        Gate::policy(Organization::class, OrganizationPolicy::class);

        Gate::before(function ($user, string $ability) {
            if ($user instanceof \App\Domain\Identity\User && $user->is_super_admin) {
                return true;
            }

            return null;
        });

        RateLimiter::for('auth', function (Request $request) {
            $email = strtolower((string) $request->input('email', ''));

            return Limit::perMinute(30)->by($email !== '' ? $email.'|'.$request->ip() : $request->ip());
        });

        RateLimiter::for('customer-requests', function (Request $request) {
            return Limit::perMinute(20)->by(optional($request->user())->id ?: $request->ip());
        });

        Cashier::useCustomerModel(\App\Domain\Identity\User::class);

        \Illuminate\Support\Facades\Route::bind('member', function (string $value) {
            return \App\Domain\Identity\User::query()->where('public_id', $value)->firstOrFail();
        });

        \Illuminate\Support\Facades\Route::bind('notification', function (string $value) {
            return \App\Domain\Notifications\AppNotification::query()->where('public_id', $value)->firstOrFail();
        });

        \Illuminate\Support\Facades\Route::bind('page', function (string $value) {
            return \App\Domain\Content\ContentPage::query()->where('slug', $value)->firstOrFail();
        });

        Event::listen(WebhookReceived::class, function (WebhookReceived $event): void {
            $type = $event->payload['type'] ?? '';
            $object = $event->payload['data']['object'] ?? [];
            $metadata = $object['metadata'] ?? [];

            if (! in_array($type, ['checkout.session.completed', 'customer.subscription.updated', 'customer.subscription.created'], true)) {
                return;
            }

            app(ApplyPaidPlan::class)->handle(
                isset($metadata['organization_id']) ? (string) $metadata['organization_id'] : null,
                isset($metadata['plan']) ? (string) $metadata['plan'] : null,
            );
        });
    }
}
