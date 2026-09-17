<?php

namespace App\Http\Api\V1;

use App\Domain\Content\ContentPage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Public CMS read + Super Admin upsert for privacy, FAQ, and related pages.
 * Audience: all | customer | field. Unpublished pages are hidden from apps.
 */
class ContentController extends Controller
{
    public function index(Request $request)
    {
        $audience = $request->query('audience');
        $admin = $request->user()?->is_super_admin && $request->boolean('all');

        $query = ContentPage::query()->orderBy('sort_order')->orderBy('title');
        if (! $admin) {
            $query->where('published', true);
            if (in_array($audience, ['customer', 'field'], true)) {
                $query->whereIn('audience', ['all', $audience]);
            }
        }

        return $query->get()->map(fn (ContentPage $page) => $this->payload($page, ! $admin));
    }

    public function adminIndex(Request $request)
    {
        abort_unless($request->user()?->is_super_admin, 403);

        return ContentPage::query()
            ->with('editor:id,name')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->map(fn (ContentPage $page) => $this->payload($page, false));
    }

    public function show(Request $request, ContentPage $page)
    {
        $admin = $request->user()?->is_super_admin;
        abort_unless($admin || $page->published, 404);

        $audience = $request->query('audience');
        abort_unless($admin || $page->visibleTo(is_string($audience) ? $audience : null), 404);

        return $this->payload($page, false);
    }

    public function upsert(Request $request, ContentPage $page)
    {
        abort_unless($request->user()?->is_super_admin, 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string'],
            'audience' => ['required', Rule::in(['all', 'customer', 'field'])],
            'published' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100'],
        ]);

        $page->fill($data);
        $page->updated_by = $request->user()->id;
        $page->save();

        activity()->causedBy($request->user())->performedOn($page)->log('content.updated');

        return $this->payload($page->fresh('editor'), false);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ContentPage $page, bool $summary): array
    {
        $data = [
            'slug' => $page->slug,
            'title' => $page->title,
            'audience' => $page->audience,
            'sort_order' => $page->sort_order,
            'published' => $page->published,
            'updated_at' => $page->updated_at?->timezone('Europe/Berlin')->toIso8601String(),
        ];

        if (! $summary) {
            $data['body'] = $page->body;
            $data['editor'] = $page->editor?->name;
        }

        return $data;
    }
}
