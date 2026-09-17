<?php

namespace App\Application\Notifications;

use App\Domain\Identity\User;
use App\Domain\Notifications\AppNotification;
use App\Domain\Organization\Organization;
use App\Enums\NotificationType;
use Illuminate\Support\Collection;

/**
 * Writes AppNotification rows for a user, all office members, or the Super Admin.
 */
final class DispatchNotification
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function send(
        User $recipient,
        NotificationType $type,
        string $title,
        string $body,
        ?Organization $organization = null,
        ?User $actor = null,
        array $data = [],
    ): void {
        if ($actor && $recipient->is($actor)) {
            return;
        }

        AppNotification::query()->create([
            'organization_id' => $organization?->id,
            'user_id' => $recipient->id,
            'actor_id' => $actor?->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function toOfficeAndPlatform(
        Organization $organization,
        NotificationType $type,
        string $title,
        string $body,
        ?User $actor = null,
        array $data = [],
        bool $includePendingOffice = true,
    ): void {
        foreach ($this->officeRecipients($organization, $includePendingOffice) as $user) {
            $this->send($user, $type, $title, $body, $organization, $actor, $data);
        }

        $this->toPlatform($type, $title, $body, $organization, $actor, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function toOffice(
        Organization $organization,
        NotificationType $type,
        string $title,
        string $body,
        ?User $actor = null,
        array $data = [],
        bool $includePendingOffice = true,
    ): void {
        foreach ($this->officeRecipients($organization, $includePendingOffice) as $user) {
            $this->send($user, $type, $title, $body, $organization, $actor, $data);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function toPlatform(
        NotificationType $type,
        string $title,
        string $body,
        ?Organization $organization = null,
        ?User $actor = null,
        array $data = [],
    ): void {
        User::query()->where('is_super_admin', true)->get()
            ->each(fn (User $admin) => $this->send($admin, $type, $title, $body, $organization, $actor, $data));
    }

    /**
     * @param  iterable<int, User>  $staff
     * @param  array<string, mixed>  $data
     */
    public function toStaff(
        iterable $staff,
        NotificationType $type,
        string $title,
        string $body,
        ?Organization $organization = null,
        ?User $actor = null,
        array $data = [],
    ): void {
        foreach ($staff as $user) {
            $this->send($user, $type, $title, $body, $organization, $actor, $data);
        }
    }

    /**
     * @return Collection<int, User>
     */
    public function officeRecipients(Organization $organization, bool $includePendingOffice = true): Collection
    {
        return $organization->users()
            ->wherePivotIn('role', ['owner', 'office'])
            ->get()
            ->filter(function (User $user) use ($includePendingOffice): bool {
                $role = $user->pivot->role ?? null;
                if ($role === 'owner') {
                    return true;
                }
                if ($includePendingOffice) {
                    return true;
                }

                return ($user->pivot->approval_status ?? 'approved') === 'approved';
            })
            ->values();
    }
}
