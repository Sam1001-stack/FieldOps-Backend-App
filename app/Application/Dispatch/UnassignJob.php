<?php

namespace App\Application\Dispatch;

use App\Application\Jobs\TransitionJobStatus;
use App\Application\Notifications\DispatchNotification;
use App\Domain\Identity\User;
use App\Domain\Jobs\ServiceJob;
use App\Enums\JobStatus;
use App\Enums\NotificationType;
use DomainException;

/**
 * Remove all Monteure from a job and put it back on the unassigned Plantafel column.
 * Allowed only before field work starts (draft / scheduled / assigned).
 */
final class UnassignJob
{
    public function handle(ServiceJob $job, User $actor): ServiceJob
    {
        $organization = $job->organization ?? \App\Domain\Organization\Organization::query()->find($job->organization_id);
        abort_unless($organization, 422, 'Mandant fehlt.');

        $job->load(['assignees', 'site', 'customer.user']);

        if ($job->assignees->isEmpty()) {
            throw new DomainException('Einsatz ist bereits nicht zugewiesen.');
        }

        if (! in_array($job->status, [JobStatus::Draft, JobStatus::Scheduled, JobStatus::Assigned], true)) {
            throw new DomainException('Einsatz kann nicht mehr entzogen werden, sobald der Monteur unterwegs oder vor Ort ist.');
        }

        $former = $job->assignees;
        $names = $former->pluck('name')->filter()->implode(', ');
        $wasAssigned = $job->status === JobStatus::Assigned;

        $job->assignees()->sync([]);

        if ($wasAssigned) {
            $job = app(TransitionJobStatus::class)->handle($job->fresh(), JobStatus::Scheduled, $actor);
        }

        activity()->causedBy($actor)->performedOn($job)->withProperties([
            'previous_monteure' => $former->pluck('public_id')->values()->all(),
        ])->log('job.unassigned');

        $job = $job->refresh()->load(['assignees', 'site', 'customer.user']);
        $notify = app(DispatchNotification::class);
        $payload = [
            'job_id' => $job->public_id,
            'status' => $job->status->value,
            'office_name' => $actor->name,
            'organization_name' => $organization->name,
        ];

        $notify->toOffice(
            $organization,
            NotificationType::JobUnassigned,
            'Zuweisung aufgehoben',
            "{$actor->name} hat „{$job->title}“ von der Tour genommen ({$names}).",
            $actor,
            $payload,
        );

        $notify->toPlatform(
            NotificationType::JobUnassigned,
            'Einsatz wieder unzugewiesen',
            "{$actor->name} hat bei {$organization->name} den Einsatz „{$job->title}“ entzogen.",
            $organization,
            $actor,
            $payload,
        );

        $notify->toStaff(
            $former,
            NotificationType::JobUnassigned,
            'Einsatz abgezogen',
            "„{$job->title}“ ist nicht mehr Ihrer Tour zugeordnet.",
            $organization,
            $actor,
            $payload,
        );

        $customerUser = $job->customer?->user;
        if ($customerUser) {
            $notify->send(
                $customerUser,
                NotificationType::JobUnassigned,
                'Neuer Termin offen',
                "Für „{$job->title}“ wird ein anderer Monteur geplant.",
                $organization,
                $actor,
                $payload,
            );
        }

        return $job;
    }
}
