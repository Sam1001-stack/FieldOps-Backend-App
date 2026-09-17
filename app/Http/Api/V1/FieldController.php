<?php

namespace App\Http\Api\V1;

use App\Domain\Catalog\CatalogItem;
use App\Domain\Jobs\JobMaterial;
use App\Domain\Jobs\JobPhoto;
use App\Domain\Jobs\JobSignature;
use App\Domain\Jobs\ServiceJob;
use App\Domain\Jobs\TimeEntry;
use App\Enums\PhotoKind;
use App\Http\Resources\JobResource;
use Illuminate\Http\Request;

class FieldController extends Controller
{
    public function addMaterial(Request $request, ServiceJob $job)
    {
        $this->authorize('update', $job);
        $data = $request->validate([
            'catalog_item_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
        ]);
        $item = CatalogItem::query()->findOrFail($data['catalog_item_id']);
        abort_unless($item->organization_id === $job->organization_id, 403);

        JobMaterial::query()->create([
            'organization_id' => $job->organization_id,
            'job_id' => $job->id,
            'catalog_item_id' => $item->id,
            'quantity' => $data['quantity'],
            'unit_price_cents' => $item->unit_price_cents,
        ]);

        return new JobResource($job->fresh()->load(['materials.catalogItem', 'photos', 'site', 'customer']));
    }

    public function photo(Request $request, ServiceJob $job)
    {
        $this->authorize('update', $job);
        $data = $request->validate([
            'photo' => ['required', 'image', 'max:12288'],
            'kind' => ['nullable', 'in:before,after,other'],
        ]);
        $path = $request->file('photo')->store("jobs/{$job->id}", 'public');
        JobPhoto::query()->create([
            'organization_id' => $job->organization_id,
            'job_id' => $job->id,
            'uploaded_by' => $request->user()->id,
            'kind' => PhotoKind::from($data['kind'] ?? 'other'),
            'path' => $path,
        ]);

        return new JobResource($job->fresh()->load(['photos', 'site', 'customer']));
    }

    public function sign(Request $request, ServiceJob $job)
    {
        $this->authorize('update', $job);
        $data = $request->validate([
            'signature' => ['required', 'string'],
            'signer_name' => ['nullable', 'string'],
        ]);
        $raw = $data['signature'];
        if (str_contains($raw, 'base64,')) {
            $raw = explode('base64,', $raw, 2)[1];
        }
        $path = "signatures/{$job->id}/".uniqid().'.png';
        \Illuminate\Support\Facades\Storage::disk('public')->put($path, base64_decode($raw));
        JobSignature::query()->create([
            'organization_id' => $job->organization_id,
            'job_id' => $job->id,
            'signer_name' => $data['signer_name'] ?? $job->customer?->name,
            'path' => $path,
            'signed_at' => now(),
        ]);

        return new JobResource($job->fresh()->load(['signatures', 'site', 'customer']));
    }

    public function startTime(Request $request, ServiceJob $job)
    {
        $this->authorize('update', $job);
        TimeEntry::query()->create([
            'organization_id' => $job->organization_id,
            'job_id' => $job->id,
            'user_id' => $request->user()->id,
            'started_at' => now(),
        ]);

        return new JobResource($job->fresh()->load(['timeEntries']));
    }

    public function stopTime(Request $request, ServiceJob $job)
    {
        $this->authorize('update', $job);
        $entry = $job->timeEntries()->where('user_id', $request->user()->id)->whereNull('ended_at')->latest()->firstOrFail();
        $entry->update(['ended_at' => now()]);

        return new JobResource($job->fresh()->load(['timeEntries']));
    }

    public function catalog(Request $request)
    {
        $this->authorize('viewAny', CatalogItem::class);

        return CatalogItem::query()->get(['id', 'public_id', 'name', 'unit', 'unit_price_cents', 'is_hourly_rate']);
    }
}
