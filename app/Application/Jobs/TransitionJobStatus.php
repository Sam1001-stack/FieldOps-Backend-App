<?php

namespace App\Application\Jobs;

use App\Application\Notifications\DispatchNotification;
use App\Domain\Identity\User;
use App\Domain\Jobs\JobStatusEvent;
use App\Domain\Jobs\JobStatusMachine;
use App\Domain\Jobs\ServiceJob;
use App\Enums\JobStatus;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use DomainException;

final class TransitionJobStatus
{
    public function handle(ServiceJob $job, JobStatus $to, User $actor): ServiceJob
    {
        $role = $actor->is_super_admin
            ? UserRole::SuperAdmin
            : $actor->roleIn($job->organization);
        if (! $role) {
            throw new DomainException('No role in this organization.');
        }

        if (! $actor->is_super_admin && $actor->hasOrgRole(UserRole::Monteur, $job->organization) && ! $job->isAssignedTo($actor)) {
            throw new DomainException('Monteur can only update assigned jobs.');
        }

        JobStatusMachine::assert($job->status, $to, $role);

        $from = $job->status;
        $job->status = $to;
        $job->save();

        JobStatusEvent::query()->create([
            'organization_id' => $job->organization_id,
            'job_id' => $job->id,
            'user_id' => $actor->id,
            'from_status' => $from->value,
            'to_status' => $to->value,
        ]);

        activity()
            ->causedBy($actor)
            ->performedOn($job)
            ->withProperties(['from' => $from->value, 'to' => $to->value])
            ->log('job.status');

        $job = $job->refresh()->load(['organization', 'customer.user', 'assignees']);
        $notify = app(DispatchNotification::class);
        $body = "{$actor->name} hat „{$job->title}“ von {$from->label()} auf {$to->label()} gesetzt.";
        $payload = [
            'job_id' => $job->public_id,
            'from' => $from->value,
            'to' => $to->value,
        ];

        $notify->toOfficeAndPlatform(
            $job->organization,
            NotificationType::JobStatus,
            'Status aktualisiert',
            $body,
            $actor,
            $payload,
        );

        $customerUser = $job->customer?->user;
        if ($customerUser) {
            $notify->send(
                $customerUser,
                NotificationType::JobStatus,
                'Ihr Auftrag',
                "Status: {$to->customerLabel()} — {$job->title}.",
                $job->organization,
                $actor,
                $payload,
            );
        }

        foreach ($job->assignees as $monteur) {
            $notify->send(
                $monteur,
                NotificationType::JobStatus,
                'Einsatz aktualisiert',
                $body,
                $job->organization,
                $actor,
                $payload,
            );
        }

        return $job;
    }
}
