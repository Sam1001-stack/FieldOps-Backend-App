<?php

namespace App\Http\Middleware;

use App\Domain\Identity\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanLimit
{
    public function handle(Request $request, Closure $next, string $resource = 'jobs'): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user?->is_super_admin) {
            return $next($request);
        }
        $org = $user?->currentOrganization;
        if (! $org) {
            return $next($request);
        }

        $limits = $org->planLimits();

        if ($resource === 'jobs' && $org->jobs_this_month >= $limits['jobs']) {
            return response()->json([
                'message' => 'Planlimit erreicht. Bitte upgraden.',
                'errors' => [],
                'code' => 'plan_limit',
            ], 402);
        }

        if ($resource === 'users' && $org->users()->count() >= $limits['users']) {
            return response()->json([
                'message' => 'Nutzerlimit erreicht.',
                'errors' => [],
                'code' => 'plan_limit',
            ], 402);
        }

        return $next($request);
    }
}
