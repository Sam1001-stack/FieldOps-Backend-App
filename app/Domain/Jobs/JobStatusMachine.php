<?php

namespace App\Domain\Jobs;

use App\Enums\UserRole;
use App\Enums\JobStatus;
use DomainException;

/**
 * Allowed job status transitions per role. Invoiced jobs are frozen.
 */
final class JobStatusMachine
{
    /**
     * @return list<JobStatus>
     */
    public static function allowed(JobStatus $from, UserRole $role): array
    {
        if ($from === JobStatus::Invoiced) {
            return [];
        }

        return match ($role) {
            UserRole::Owner, UserRole::Office, UserRole::SuperAdmin => self::officeFrom($from),
            UserRole::Monteur => self::monteurFrom($from),
            UserRole::Customer => self::customerFrom($from),
            default => [],
        };
    }

    public static function assert(JobStatus $from, JobStatus $to, UserRole $role): void
    {
        if ($from === JobStatus::Invoiced) {
            throw new DomainException('Berechnete Einsätze können nicht mehr geändert werden.');
        }

        if ($from === JobStatus::Cancelled && ! (in_array($role, [UserRole::Owner, UserRole::SuperAdmin], true) && $to === JobStatus::Draft)) {
            throw new DomainException('Stornierte Einsätze darf nur der Inhaber wieder auf Entwurf setzen.');
        }

        $allowed = self::allowed($from, $role);
        if ($from === JobStatus::Cancelled && in_array($role, [UserRole::Owner, UserRole::SuperAdmin], true) && $to === JobStatus::Draft) {
            return;
        }

        if (! in_array($to, $allowed, true)) {
            throw new DomainException("Statuswechsel {$from->label()} → {$to->label()} ist für {$role->label()} nicht erlaubt.");
        }
    }

    /**
     * @return list<JobStatus>
     */
    private static function officeFrom(JobStatus $from): array
    {
        return match ($from) {
            JobStatus::Draft => [JobStatus::Scheduled, JobStatus::Assigned, JobStatus::Cancelled],
            JobStatus::Scheduled => [JobStatus::Assigned, JobStatus::Cancelled],
            JobStatus::Assigned => [JobStatus::Scheduled, JobStatus::EnRoute, JobStatus::Cancelled],
            JobStatus::EnRoute, JobStatus::OnSite, JobStatus::WaitingParts => [JobStatus::Cancelled],
            JobStatus::Completed => [JobStatus::Invoiced],
            default => [],
        };
    }

    /**
     * @return list<JobStatus>
     */
    private static function monteurFrom(JobStatus $from): array
    {
        return match ($from) {
            JobStatus::Assigned => [JobStatus::EnRoute],
            JobStatus::EnRoute => [JobStatus::OnSite],
            JobStatus::OnSite => [JobStatus::WaitingParts, JobStatus::Completed],
            JobStatus::WaitingParts => [JobStatus::OnSite, JobStatus::Completed],
            default => [],
        };
    }

    /**
     * @return list<JobStatus>
     */
    private static function customerFrom(JobStatus $from): array
    {
        return match ($from) {
            JobStatus::Draft, JobStatus::Scheduled, JobStatus::Assigned => [JobStatus::Cancelled],
            default => [],
        };
    }
}
