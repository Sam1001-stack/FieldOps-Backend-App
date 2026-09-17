<?php

namespace App\Application\Dispatch;

use App\Domain\Identity\User;
use App\Domain\Jobs\ServiceJob;
use App\Domain\Organization\Organization;
use App\Enums\UserRole;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class GetDispatchBoard
{
    /**
     * @return array{monteure: Collection<int, array<string, mixed>>, unassigned: Collection<int, ServiceJob>}
     */
    public function handle(Organization $organization, ?Carbon $day = null): array
    {
        $day ??= now('Europe/Berlin');

        $monteure = $organization->users()
            ->wherePivot('role', UserRole::Monteur->value)
            ->get();

        $jobs = ServiceJob::query()
            ->with(['site', 'customer', 'assignees', 'photos'])
            ->where(function ($q) use ($day): void {
                $q->whereDate('scheduled_start', $day->toDateString())
                    ->orWhere(function ($q2) use ($day): void {
                        $q2->whereNull('scheduled_start')->whereDate('created_at', $day->toDateString());
                    });
            })
            ->get();

        $columns = $monteure->map(function (User $monteur) use ($jobs) {
            $assigned = $jobs->filter(fn (ServiceJob $job) => $job->assignees->contains('id', $monteur->id));

            return [
                'monteur' => $monteur,
                'jobs' => $assigned->values(),
            ];
        });

        $unassigned = $jobs->filter(fn (ServiceJob $job) => $job->assignees->isEmpty())->values();

        return [
            'monteure' => $columns,
            'unassigned' => $unassigned,
            'day' => $day->toDateString(),
        ];
    }
}
