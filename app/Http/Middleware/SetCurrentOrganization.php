<?php

namespace App\Http\Middleware;

use App\Domain\Identity\User;
use App\Domain\Organization\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the current tenant. Super Admin may pass X-Organization-Id (UUID).
 * Other users use users.current_organization_id. Sets Spatie team id.
 */
class SetCurrentOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user?->is_super_admin) {
            $orgId = $this->resolveSuperAdminOrganizationId($user, $request);
            if ($orgId) {
                app()->instance('current_organization_id', $orgId);
                setPermissionsTeamId($orgId);
            }

            return $next($request);
        }

        if ($user?->current_organization_id) {
            app()->instance('current_organization_id', (int) $user->current_organization_id);
            setPermissionsTeamId($user->current_organization_id);
        }

        return $next($request);
    }

    private function resolveSuperAdminOrganizationId(User $user, Request $request): ?int
    {
        $raw = $request->header('X-Organization-Id') ?: $request->query('organization_id');
        if (is_string($raw) && $raw !== '') {
            $org = Organization::query()->where('public_id', $raw)->first();
            if ($org) {
                if ((int) $user->current_organization_id !== (int) $org->id) {
                    $user->forceFill(['current_organization_id' => $org->id])->save();
                }
                $user->setRelation('currentOrganization', $org);

                return (int) $org->id;
            }
        }

        if ($user->current_organization_id) {
            return (int) $user->current_organization_id;
        }

        $fallback = Organization::query()->first();
        if ($fallback) {
            $user->forceFill(['current_organization_id' => $fallback->id])->save();
            $user->setRelation('currentOrganization', $fallback);

            return (int) $fallback->id;
        }

        return null;
    }
}
