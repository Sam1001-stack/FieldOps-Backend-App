<?php

namespace App\Application\Dispatch;

use App\Application\Jobs\TransitionJobStatus;
use App\Application\Notifications\DispatchNotification;
use App\Domain\Identity\User;
use App\Domain\Jobs\ServiceJob;
use App\Enums\JobStatus;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use DomainException;

/**
 * Assign a job to a Monteur, move status to assigned, notify staff, office, and Super Admin.
 */
final class AssignJob
{
    public function handle(ServiceJob $job, User $monteur, User $actor): ServiceJob
    {
        $organization = $job->organization ?? \App\Domain\Organization\Organization::query()->find($job->organization_id);
        abort_unless($organization, 422, 'Mandant fehlt.');

        if ($monteur->roleIn($organization) !== UserRole::Monteur) {
            throw new DomainException('Nur Monteure können Einsätzen zugewiesen werden.');
        }

        $job->assignees()->syncWithoutDetaching([$monteur->id => ['organization_id' => $job->organization_id]]);

        if (in_array($job->status, [JobStatus::Draft, JobStatus::Scheduled], true)) {
            app(TransitionJobStatus::class)->handle($job, JobStatus::Assigned, $actor);
        }

        activity()->causedBy($actor)->performedOn($job)->withProperties([
            'monteur_id' => $monteur->public_id,
        ])->log('job.assigned');

        $job = $job->refresh()->load(['assignees', 'site', 'customer.user']);
        $notify = app(DispatchNotification::class);
        $payload = [
            'job_id' => $job->public_id,
            'monteur_id' => $monteur->public_id,
            'monteur_name' => $monteur->name,
            'office_name' => $actor->name,
            'organization_name' => $organization->name,
            'status' => $job->status->value,
        ];

        $notify->toOffice(
            $organization,
            NotificationType::JobAssigned,
            'Einsatz zugewiesen',
            "{$actor->name} hat „{$job->title}“ an {$monteur->name} zugewiesen.",
            $actor,
            $payload,
        );

        $notify->toPlatform(
            NotificationType::JobAssigned,
            'Büro hat Einsatz zugewiesen',
            "{$actor->name} (Büro) hat bei {$organization->name} den Einsatz „{$job->title}“ an {$monteur->name} zugewiesen.",
            $organization,
            $actor,
            $payload,
        );

        $notify->toStaff(
            $job->assignees,
            NotificationType::JobAssigned,
            'Neuer Einsatz',
            "Ihnen wurde „{$job->title}“ zugewiesen · {$job->site?->addressLine()}.",
            $organization,
            $actor,
            $payload,
        );

        $customerUser = $job->customer?->user;
        if ($customerUser) {
            $notify->send(
                $customerUser,
                NotificationType::JobAssigned,
                'Monteur geplant',
                "{$monteur->name} übernimmt Ihren Auftrag „{$job->title}“.",
                $organization,
                $actor,
                $payload,
            );
        }

        return $job;
    }
}
