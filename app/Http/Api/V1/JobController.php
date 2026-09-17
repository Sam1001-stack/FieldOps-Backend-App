<?php

namespace App\Http\Api\V1;

use App\Application\Jobs\CreateJob;
use App\Application\Jobs\TransitionJobStatus;
use App\Domain\Jobs\ServiceJob;
use App\Enums\JobStatus;
use App\Enums\UserRole;
use App\Http\Resources\JobResource;
use Illuminate\Http\Request;

class JobController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', ServiceJob::class);
        $user = $request->user();

        $query = ServiceJob::query()->with(['customer', 'site', 'assignees', 'photos']);

        if ($user->roleIn() === UserRole::Monteur) {
            $query->whereHas('assignees', fn ($q) => $q->where('users.id', $user->id));
        }
        if ($user->roleIn() === UserRole::Customer) {
            $query->whereHas('customer', fn ($q) => $q->where('user_id', $user->id));
        }

        return JobResource::collection($query->latest()->paginate(50));
    }

    public function show(Request $request, ServiceJob $job)
    {
        $this->authorize('view', $job);

        $job->load([
            'customer', 'site', 'assignees', 'photos', 'checklistItems',
            'materials.catalogItem', 'signatures', 'timeEntries', 'invoice', 'statusEvents',
        ]);

        return new JobResource($job);
    }

    public function store(Request $request, CreateJob $action)
    {
        $this->authorize('create', ServiceJob::class);
        $data = $request->validate([
            'customer_id' => ['nullable', 'integer'],
            'site_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'urgency' => ['nullable', 'in:normal,notdienst'],
            'gewerk' => ['nullable', 'string'],
            'scheduled_start' => ['nullable', 'date'],
            'scheduled_end' => ['nullable', 'date'],
            'window_start' => ['nullable', 'date'],
            'window_end' => ['nullable', 'date'],
            'street' => ['nullable', 'string', 'max:180'],
            'zip' => ['nullable', 'string', 'max:16'],
            'city' => ['nullable', 'string', 'max:80'],
        ]);

        $org = $request->user()->currentOrganization;
        abort_unless($org, 422, 'Kein Mandant ausgewählt.');
        $job = $action->handle($org, $request->user(), $data);

        return (new JobResource($job))->response()->setStatusCode(201);
    }

    public function transition(Request $request, ServiceJob $job, TransitionJobStatus $action)
    {
        $this->authorize('update', $job);
        $data = $request->validate([
            'status' => ['required', 'string'],
        ]);

        $job = $action->handle($job, JobStatus::from($data['status']), $request->user());

        return new JobResource($job->load(['customer', 'site', 'assignees', 'photos', 'checklistItems']));
    }

    public function update(Request $request, ServiceJob $job)
    {
        $this->authorize('update', $job);
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'urgency' => ['nullable', 'in:normal,notdienst'],
            'scheduled_start' => ['nullable', 'date'],
            'scheduled_end' => ['nullable', 'date'],
        ]);
        $job->update($data);

        return new JobResource($job->fresh()->load(['customer', 'site', 'assignees', 'photos']));
    }

    public function destroy(ServiceJob $job)
    {
        $this->authorize('update', $job);
        $job->delete();

        return response()->json(['message' => 'Einsatz gelöscht.']);
    }
}
