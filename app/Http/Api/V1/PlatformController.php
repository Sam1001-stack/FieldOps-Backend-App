<?php

namespace App\Http\Api\V1;

use App\Application\Organization\CreateOrganization;
use App\Domain\Identity\User;
use App\Domain\Invoicing\Invoice;
use App\Domain\Jobs\ServiceJob;
use App\Domain\Organization\Organization;
use App\Enums\InvoiceStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PlatformController
{
    public function organizations(Request $request)
    {
        abort_unless($request->user()?->is_super_admin, 403);

        return $this->organizationPayload();
    }

    public function store(Request $request, CreateOrganization $create)
    {
        abort_unless($request->user()?->is_super_admin, 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'street' => ['nullable', 'string', 'max:180'],
            'zip' => ['nullable', 'string', 'max:16'],
            'city' => ['nullable', 'string', 'max:80'],
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_email' => ['required', 'email', 'unique:users,email'],
            'owner_password' => ['required', 'string', 'min:8'],
        ]);

        $org = $create->handle($request->user(), $data);
        $payload = $this->organizationPayload()->firstWhere('id', $org->public_id);

        return response()->json($payload, 201);
    }

    public function overview(Request $request)
    {
        abort_unless($request->user()?->is_super_admin, 403);

        $today = now('Europe/Berlin')->toDateString();

        $jobs = ServiceJob::query()
            ->withoutGlobalScopes()
            ->with(['organization', 'customer', 'site', 'assignees'])
            ->latest()
            ->limit(20)
            ->get();

        $invoices = Invoice::query()
            ->withoutGlobalScopes()
            ->with(['organization', 'customer'])
            ->latest()
            ->limit(15)
            ->get();

        $activity = collect();
        if (Schema::hasTable('activity_log')) {
            $activity = DB::table('activity_log as a')
                ->leftJoin('users as u', 'u.id', '=', 'a.causer_id')
                ->orderByDesc('a.id')
                ->limit(5)
                ->get(['a.description', 'a.created_at', 'u.name as actor', 'a.properties']);
        }

        return [
            'stats' => [
                'organizations' => Organization::query()->count(),
                'users' => User::query()->where('is_super_admin', false)->count(),
                'jobs' => ServiceJob::query()->withoutGlobalScopes()->count(),
                'jobs_today' => ServiceJob::query()->withoutGlobalScopes()
                    ->where(function ($q) use ($today): void {
                        $q->whereDate('scheduled_start', $today)
                            ->orWhereDate('created_at', $today);
                    })
                    ->count(),
                'open_notdienst' => ServiceJob::query()->withoutGlobalScopes()
                    ->where('urgency', 'notdienst')
                    ->whereNotIn('status', ['completed', 'invoiced', 'cancelled'])
                    ->count(),
                'invoices' => Invoice::query()->withoutGlobalScopes()->count(),
                'revenue_cents' => (int) Invoice::query()->withoutGlobalScopes()
                    ->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::Paid->value])
                    ->sum('total_cents'),
                'queue' => config('queue.default'),
                'failed_jobs' => Schema::hasTable('failed_jobs')
                    ? (int) DB::table('failed_jobs')->count()
                    : 0,
            ],
            'organizations' => $this->organizationPayload(),
            'jobs' => $jobs->map(fn (ServiceJob $job) => [
                'id' => $job->public_id,
                'title' => $job->title,
                'status' => $job->status->value,
                'urgency' => $job->urgency->value,
                'organization' => $job->organization?->name,
                'customer' => $job->customer?->name,
                'site' => $job->site?->addressLine(),
                'assignees' => $job->assignees->pluck('name')->values(),
                'scheduled_start' => $job->scheduled_start?->timezone('Europe/Berlin')->toIso8601String(),
            ]),
            'invoices' => $invoices->map(fn (Invoice $invoice) => [
                'id' => $invoice->public_id,
                'number' => $invoice->number,
                'status' => $invoice->status->value,
                'total_cents' => $invoice->total_cents,
                'organization' => $invoice->organization?->name,
                'customer' => $invoice->customer?->name,
            ]),
            'activity' => $activity->map(fn ($row) => [
                'description' => $row->description,
                'actor' => $row->actor,
                'at' => $row->created_at,
            ]),
        ];
    }

    public function switchOrganization(Request $request)
    {
        abort_unless($request->user()?->is_super_admin, 403);
        $data = $request->validate(['organization_id' => ['required', 'uuid']]);
        $org = Organization::query()->where('public_id', $data['organization_id'])->firstOrFail();
        $request->user()->forceFill(['current_organization_id' => $org->id])->save();

        return [
            'organization' => [
                'id' => $org->public_id,
                'name' => $org->name,
                'plan' => $org->plan,
            ],
        ];
    }

    public function suspend(Request $request, Organization $organization)
    {
        abort_unless($request->user()?->is_super_admin, 403);
        $organization->update(['suspended' => ! $organization->suspended]);
        activity()->causedBy($request->user())->performedOn($organization)->log('org.suspend.toggle');

        return ['suspended' => $organization->suspended];
    }

    public function impersonate(Request $request)
    {
        abort_unless($request->user()?->is_super_admin, 403);
        $data = $request->validate(['user_id' => ['required', 'uuid']]);
        $target = User::query()->where('public_id', $data['user_id'])->firstOrFail();
        abort_if($target->is_super_admin, 422, 'Impersonation des Super-Admins ist nicht erlaubt.');
        $token = $target->createToken('impersonation', ['impersonated'])->plainTextToken;
        activity()->causedBy($request->user())->performedOn($target)->log('impersonation');

        return [
            'token' => $token,
            'user' => [
                'id' => $target->public_id,
                'name' => $target->name,
                'email' => $target->email,
            ],
            'expires_hint' => '15m',
        ];
    }

    public function health(Request $request)
    {
        abort_unless($request->user()?->is_super_admin, 403);

        return [
            'failed_jobs' => Schema::hasTable('failed_jobs')
                ? (int) DB::table('failed_jobs')->count()
                : 0,
            'queue' => config('queue.default'),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function organizationPayload()
    {
        return Organization::query()->with(['settings', 'users'])->get()->map(function (Organization $o) {
            $members = $o->users->map(fn (User $u) => [
                'id' => $u->public_id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->pivot->role,
            ])->values();

            return [
                'id' => $o->public_id,
                'name' => $o->name,
                'plan' => $o->plan,
                'suspended' => $o->suspended,
                'users' => $members->count(),
                'jobs' => ServiceJob::query()->withoutGlobalScopes()->where('organization_id', $o->id)->count(),
                'city' => $o->settings?->city,
                'members' => $members,
                'owner' => $members->firstWhere('role', 'owner'),
            ];
        });
    }
}
