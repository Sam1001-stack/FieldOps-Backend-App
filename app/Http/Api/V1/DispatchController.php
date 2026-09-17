<?php

namespace App\Http\Api\V1;

use App\Application\Dispatch\AssignJob;
use App\Application\Dispatch\GetDispatchBoard;
use App\Domain\Identity\User;
use App\Domain\Jobs\ServiceJob;
use App\Http\Resources\JobResource;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DispatchController extends Controller
{
    public function board(Request $request, GetDispatchBoard $action)
    {
        $this->authorize('viewAny', ServiceJob::class);
        $day = $request->query('day')
            ? Carbon::parse($request->query('day'), 'Europe/Berlin')
            : now('Europe/Berlin');

        $board = $action->handle($request->user()->currentOrganization, $day);

        return [
            'day' => $board['day'],
            'unassigned' => JobResource::collection($board['unassigned']),
            'columns' => $board['monteure']->map(fn ($col) => [
                'monteur' => [
                    'id' => $col['monteur']->public_id,
                    'name' => $col['monteur']->name,
                    'phone' => $col['monteur']->phone,
                ],
                'jobs' => JobResource::collection($col['jobs']),
            ]),
        ];
    }

    public function assign(Request $request, ServiceJob $job, AssignJob $action)
    {
        $this->authorize('dispatch', ServiceJob::class);
        $data = $request->validate([
            'monteur_id' => ['required', 'uuid'],
        ]);

        $monteur = User::query()->where('public_id', $data['monteur_id'])->firstOrFail();
        $job = $action->handle($job, $monteur, $request->user());

        return new JobResource($job);
    }
}
