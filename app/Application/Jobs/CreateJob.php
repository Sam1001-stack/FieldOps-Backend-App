<?php

namespace App\Application\Jobs;

use App\Application\Notifications\DispatchNotification;
use App\Domain\CRM\Customer;
use App\Domain\CRM\Site;
use App\Domain\Identity\User;
use App\Domain\Jobs\JobChecklistItem;
use App\Domain\Jobs\ServiceJob;
use App\Domain\Organization\Organization;
use App\Enums\JobStatus;
use App\Enums\NotificationType;
use App\Enums\Urgency;
use App\Enums\UserRole;
use DomainException;

final class CreateJob
{
    public function __construct(private DispatchNotification $notify) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Organization $organization, User $actor, array $data): ServiceJob
    {
        if (! $actor->canCreateJobs($organization)) {
            throw new DomainException($this->deniedMessage($actor, $organization));
        }

        $customer = $this->resolveCustomer($organization, $actor, $data);
        abort_unless($customer->organization_id === $organization->id, 403);

        $site = $this->resolveSite($organization, $customer, $data);

        $status = $actor->hasOrgRole(UserRole::Customer, $organization)
            ? JobStatus::Draft
            : JobStatus::from($data['status'] ?? JobStatus::Draft->value);

        $job = ServiceJob::query()->create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'site_id' => $site->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $status,
            'urgency' => Urgency::from($data['urgency'] ?? Urgency::Normal->value),
            'gewerk' => $data['gewerk'] ?? 'shk',
            'scheduled_start' => $data['scheduled_start'] ?? null,
            'scheduled_end' => $data['scheduled_end'] ?? null,
            'window_start' => $data['window_start'] ?? null,
            'window_end' => $data['window_end'] ?? null,
        ]);

        foreach (['Druck prüfen', 'Fehlercode notieren', 'Kunde informiert'] as $i => $label) {
            JobChecklistItem::query()->create([
                'organization_id' => $organization->id,
                'job_id' => $job->id,
                'label' => $label,
                'position' => $i,
            ]);
        }

        $organization->increment('jobs_this_month');

        activity()->causedBy($actor)->performedOn($job)->log('job.created');

        $who = $actor->hasOrgRole(UserRole::Customer, $organization) ? $customer->name : $actor->name;
        $this->notify->toOfficeAndPlatform(
            $organization,
            NotificationType::JobCreated,
            'Neuer Einsatz',
            "{$who} hat „{$job->title}“ angelegt ({$site->addressLine()}).",
            $actor,
            [
                'job_id' => $job->public_id,
                'customer_id' => $customer->public_id,
                'status' => $job->status->value,
            ],
        );

        return $job->load(['customer', 'site', 'assignees', 'photos', 'materials.catalogItem', 'checklistItems']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveCustomer(Organization $organization, User $actor, array $data): Customer
    {
        if ($actor->hasOrgRole(UserRole::Customer, $organization)) {
            $customer = Customer::query()
                ->where('organization_id', $organization->id)
                ->where('user_id', $actor->id)
                ->first();
            abort_unless($customer, 422, 'Kein Kundenprofil gefunden.');

            return $customer;
        }

        abort_unless(! empty($data['customer_id']), 422, 'Kunde fehlt.');

        return Customer::query()
            ->where('organization_id', $organization->id)
            ->where('id', $data['customer_id'])
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveSite(Organization $organization, Customer $customer, array $data): Site
    {
        if (! empty($data['site_id'])) {
            return Site::query()
                ->where('organization_id', $organization->id)
                ->where('customer_id', $customer->id)
                ->where('id', $data['site_id'])
                ->firstOrFail();
        }

        if (! empty($data['street'])) {
            $site = $customer->sites()->first();
            $payload = [
                'organization_id' => $organization->id,
                'customer_id' => $customer->id,
                'street' => $data['street'],
                'zip' => $data['zip'] ?? $site?->zip ?? '',
                'city' => $data['city'] ?? $site?->city ?? '',
            ];
            if ($site) {
                $site->update($payload);

                return $site->fresh();
            }

            return Site::query()->create($payload);
        }

        $site = $customer->sites()->first();
        abort_unless($site, 422, 'Keine Adresse hinterlegt.');

        return $site;
    }

    private function deniedMessage(User $actor, Organization $organization): string
    {
        return match ($actor->roleIn($organization)) {
            UserRole::Customer => 'Das Büro muss Ihr Konto zuerst bestätigen, bevor Sie Einsätze anlegen können.',
            UserRole::Office => 'Der Super-Admin muss Ihr Büro-Konto zuerst freigeben, bevor Sie Einsätze anlegen können.',
            default => 'Keine Berechtigung zum Anlegen von Einsätzen.',
        };
    }
}
