<?php

namespace App\Http\Api\V1;

use App\Application\Identity\AttachMember;
use App\Domain\CRM\Customer;
use App\Domain\Identity\User;
use App\Domain\Invoicing\Invoice;
use App\Domain\Jobs\JobStatusEvent;
use App\Domain\Jobs\ServiceJob;
use App\Domain\Jobs\TimeEntry;
use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        $org = $request->user()->currentOrganization;
        abort_unless($org, 404);
        $this->authorize('view', $org);

        $query = $org->users();
        $group = $request->query('group');
        if (in_array($group, ['customers', 'staff', 'office'], true)) {
            $query->wherePivotIn('role', $this->rolesFor($group));
        }

        return $query->get()->map(fn (User $user) => $this->memberPayload($user));
    }

    public function logs(Request $request)
    {
        $org = $request->user()->currentOrganization;
        abort_unless($org, 404);
        $this->authorize('view', $org);

        $data = $request->validate([
            'group' => ['required', Rule::in(['customers', 'staff', 'office'])],
        ]);

        $users = $org->users()->wherePivotIn('role', $this->rolesFor($data['group']))->get();
        $ids = $users->pluck('id');
        $byId = $users->keyBy('id');

        $items = collect();

        if ($ids->isNotEmpty() && Schema::hasTable('activity_log')) {
            $activity = DB::table('activity_log as a')
                ->leftJoin('users as u', 'u.id', '=', 'a.causer_id')
                ->where(function ($q) use ($ids): void {
                    $q->whereIn('a.causer_id', $ids);
                })
                ->orderByDesc('a.id')
                ->limit(80)
                ->get(['a.id', 'a.description', 'a.created_at', 'a.causer_id', 'a.properties', 'u.name as actor']);

            foreach ($activity as $row) {
                $props = $this->decodeProps($row->properties);
                $member = $byId->get($row->causer_id);
                $items->push($this->logItem(
                    'act-'.$row->id,
                    (string) $row->description,
                    $row->actor,
                    $row->created_at,
                    $member?->public_id,
                    $this->activityDetail($row->description, $props),
                    $props,
                ));
            }
        }

        if ($data['group'] === 'staff' && $ids->isNotEmpty()) {
            $assignments = DB::table('job_assignments as ja')
                ->join('service_jobs as j', 'j.id', '=', 'ja.job_id')
                ->join('users as u', 'u.id', '=', 'ja.user_id')
                ->where('ja.organization_id', $org->id)
                ->whereIn('ja.user_id', $ids)
                ->orderByDesc('ja.id')
                ->limit(40)
                ->get(['ja.id', 'ja.created_at', 'ja.user_id', 'u.name as actor', 'j.title', 'j.status', 'u.public_id as member_id']);

            foreach ($assignments as $row) {
                $items->push($this->logItem(
                    'assign-'.$row->id,
                    'job.assigned',
                    $row->actor,
                    $row->created_at,
                    $row->member_id,
                    $row->title.' · '.$row->status,
                ));
            }

            $times = TimeEntry::query()
                ->where('organization_id', $org->id)
                ->whereIn('user_id', $ids)
                ->with('job')
                ->latest('started_at')
                ->limit(40)
                ->get();

            foreach ($times as $entry) {
                $member = $byId->get($entry->user_id);
                $items->push($this->logItem(
                    'time-'.$entry->id,
                    'time.logged',
                    $member?->name,
                    $entry->started_at,
                    $member?->public_id,
                    ($entry->job?->title ?? 'Einsatz').' · '.$entry->minutes().' Min.',
                ));
            }
        }

        if ($data['group'] === 'customers' && $ids->isNotEmpty()) {
            $customerIds = Customer::query()
                ->where('organization_id', $org->id)
                ->whereIn('user_id', $ids)
                ->pluck('id');

            $jobs = ServiceJob::query()
                ->where('organization_id', $org->id)
                ->whereIn('customer_id', $customerIds)
                ->with('customer')
                ->latest()
                ->limit(40)
                ->get();

            foreach ($jobs as $job) {
                $member = $users->firstWhere('id', $job->customer?->user_id);
                $items->push($this->logItem(
                    'job-'.$job->id,
                    'job.created',
                    $member?->name ?? $job->customer?->name,
                    $job->created_at,
                    $member?->public_id,
                    $job->title.' · '.$job->status->value,
                ));
            }
        }

        if ($data['group'] === 'office' && $ids->isNotEmpty()) {
            $events = JobStatusEvent::query()
                ->where('organization_id', $org->id)
                ->whereIn('user_id', $ids)
                ->with('job')
                ->latest()
                ->limit(40)
                ->get();

            foreach ($events as $event) {
                $member = $byId->get($event->user_id);
                $items->push($this->logItem(
                    'status-'.$event->id,
                    'job.status',
                    $member?->name,
                    $event->created_at,
                    $member?->public_id,
                    ($event->job?->title ?? 'Einsatz').' · '.$event->from_status.' → '.$event->to_status,
                ));
            }

            $invoices = Invoice::query()
                ->where('organization_id', $org->id)
                ->with('customer')
                ->latest()
                ->limit(20)
                ->get();

            foreach ($invoices as $invoice) {
                $owner = $users->first(fn (User $user) => $user->pivot->role === 'owner') ?? $users->first();
                $items->push($this->logItem(
                    'inv-'.$invoice->id,
                    $invoice->sent_at ? 'invoice.sent' : 'invoice.created',
                    $owner?->name,
                    $invoice->sent_at ?? $invoice->created_at,
                    $owner?->public_id,
                    ($invoice->number ?: 'Entwurf').' · '.($invoice->customer?->name ?? ''),
                ));
            }
        }

        return $items
            ->sortByDesc('at')
            ->unique('id')
            ->take(60)
            ->values();
    }

    public function store(Request $request, AttachMember $attach)
    {
        $org = $request->user()->currentOrganization;
        abort_unless($org, 404);
        $this->authorize('update', $org);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['owner', 'office', 'monteur', 'customer', 'accountant'])],
            'phone' => ['nullable', 'string'],
        ]);

        $user = User::query()->firstOrCreate(
            ['email' => $data['email']],
            [
                'name' => $data['name'],
                'password' => $data['password'],
                'phone' => $data['phone'] ?? null,
            ],
        );

        $attach->handle($org, $user, UserRole::from($data['role']), $request->user());
        $user->forceFill([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? $user->phone,
            'current_organization_id' => $org->id,
        ])->save();

        activity()->causedBy($request->user())->performedOn($user)->withProperties([
            'role' => $data['role'],
        ])->log('member.created');

        return response()->json([
            'id' => $user->public_id,
            'email' => $user->email,
            'role' => $data['role'],
        ], 201);
    }

    public function update(Request $request, User $member, AttachMember $attach)
    {
        $org = $request->user()->currentOrganization;
        abort_unless($org, 404);
        $this->authorize('update', $org);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'phone' => ['nullable', 'string'],
            'role' => ['sometimes', Rule::in(['owner', 'office', 'monteur', 'customer', 'accountant'])],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        if (isset($data['name']) || array_key_exists('phone', $data)) {
            $member->forceFill(collect($data)->only(['name', 'phone'])->all())->save();
        }
        if (! empty($data['password'])) {
            $member->forceFill(['password' => $data['password']])->save();
        }
        if (isset($data['role'])) {
            $attach->handle($org, $member, UserRole::from($data['role']), $request->user());
        }

        return [
            'id' => $member->public_id,
            'name' => $member->name,
            'role' => $org->users()->where('users.id', $member->id)->first()?->pivot->role,
        ];
    }

    public function destroy(Request $request, User $member)
    {
        $org = $request->user()->currentOrganization;
        abort_unless($org, 404);
        $this->authorize('update', $org);
        abort_if($member->is($request->user()), 422, 'Eigenes Konto kann nicht entfernt werden.');

        activity()->causedBy($request->user())->performedOn($member)->withProperties([
            'name' => $member->name,
            'email' => $member->email,
        ])->log('member.removed');

        $org->users()->detach($member->id);
        if ($member->organizations()->count() === 0 && ! $member->is_super_admin) {
            $member->delete();
        }

        return response()->json(['message' => 'Mitglied entfernt.']);
    }

    public function approve(Request $request, User $member)
    {
        $org = $request->user()->currentOrganization;
        abort_unless($org, 404);
        $this->authorize('approveOffice', $org);

        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
        ]);

        abort_unless(
            $org->users()->where('users.id', $member->id)->wherePivot('role', UserRole::Office->value)->exists(),
            422,
            'Nur Büro-Konten können so freigegeben werden.',
        );

        $status = \App\Enums\ApprovalStatus::from($data['status']);
        $org->users()->updateExistingPivot($member->id, [
            'approval_status' => $status->value,
            'approved_at' => $status === \App\Enums\ApprovalStatus::Approved ? now() : null,
            'approved_by' => $request->user()->id,
        ]);

        $notify = app(\App\Application\Notifications\DispatchNotification::class);
        $type = $status === \App\Enums\ApprovalStatus::Approved
            ? \App\Enums\NotificationType::OfficeApproved
            : \App\Enums\NotificationType::OfficeRejected;
        $title = $status === \App\Enums\ApprovalStatus::Approved ? 'Büro freigegeben' : 'Büro abgelehnt';
        $body = $status === \App\Enums\ApprovalStatus::Approved
            ? "{$member->name} darf jetzt Einsätze anlegen."
            : "{$member->name} wurde nicht freigegeben.";

        $notify->toPlatform($type, $title, $body, $org, $request->user(), [
            'member_id' => $member->public_id,
            'status' => $status->value,
        ]);
        $notify->send(
            $member,
            $type,
            $title,
            $status === \App\Enums\ApprovalStatus::Approved
                ? 'Der Super-Admin hat Ihr Büro-Konto freigegeben. Sie können jetzt Einsätze anlegen.'
                : 'Der Super-Admin hat Ihr Büro-Konto abgelehnt.',
            $org,
            $request->user(),
            ['status' => $status->value],
        );

        activity()->causedBy($request->user())->performedOn($member)->withProperties([
            'status' => $status->value,
        ])->log('office.approved');

        $fresh = $org->users()->where('users.id', $member->id)->firstOrFail();

        return $this->memberPayload($fresh);
    }

    /**
     * @return list<string>
     */
    private function rolesFor(string $group): array
    {
        return match ($group) {
            'customers' => ['customer'],
            'staff' => ['monteur'],
            default => ['owner', 'office', 'accountant'],
        };
    }

    private function memberPayload(User $user): array
    {
        $role = $user->pivot->role;
        $approval = $user->pivot->approval_status ?? 'approved';

        return [
            'id' => $user->public_id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $role,
            'role_label' => UserRole::tryFrom($role)?->label() ?? $role,
            'approval_status' => $approval,
            'approval_label' => \App\Enums\ApprovalStatus::tryFrom((string) $approval)?->label() ?? $approval,
        ];
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array{id: string, description: string, actor: ?string, at: mixed, member_id: ?string, detail: ?string, properties: array<string, mixed>}
     */
    private function logItem(string $id, string $description, ?string $actor, mixed $at, ?string $memberId = null, ?string $detail = null, array $properties = []): array
    {
        $when = $at;
        if ($at instanceof \DateTimeInterface) {
            $when = \Illuminate\Support\Carbon::parse($at)->timezone('Europe/Berlin')->toIso8601String();
        } elseif (is_string($at) && $at !== '') {
            $when = \Illuminate\Support\Carbon::parse($at)->timezone('Europe/Berlin')->toIso8601String();
        }

        return [
            'id' => $id,
            'description' => $description,
            'actor' => $actor,
            'at' => $when,
            'member_id' => $memberId,
            'detail' => $detail,
            'properties' => $properties,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeProps(mixed $properties): array
    {
        if (is_array($properties)) {
            return $properties;
        }
        if (is_object($properties)) {
            return (array) $properties;
        }
        if (is_string($properties) && $properties !== '') {
            $decoded = json_decode($properties, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function activityDetail(string $description, array $properties): ?string
    {
        if ($description === 'job.status' && isset($properties['from'], $properties['to'])) {
            return $properties['from'].' → '.$properties['to'];
        }
        if ($description === 'member.created' && isset($properties['role'])) {
            return UserRole::tryFrom((string) $properties['role'])?->label() ?? (string) $properties['role'];
        }

        return isset($properties['job']) ? (string) $properties['job'] : null;
    }
}
