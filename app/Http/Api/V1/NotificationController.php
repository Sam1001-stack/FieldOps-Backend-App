<?php

namespace App\Http\Api\V1;

use App\Domain\Notifications\AppNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $items = AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->with('actor:id,name,public_id')
            ->latest()
            ->limit(80)
            ->get();

        return $items->map(fn (AppNotification $n) => $this->payload($n));
    }

    public function unreadCount(Request $request)
    {
        return [
            'count' => AppNotification::query()
                ->where('user_id', $request->user()->id)
                ->whereNull('read_at')
                ->count(),
        ];
    }

    public function markRead(Request $request, AppNotification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 404);
        if (! $notification->read_at) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return $this->payload($notification->fresh());
    }

    public function markAllRead(Request $request)
    {
        AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return ['message' => 'Alle gelesen.'];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(AppNotification $notification): array
    {
        return [
            'id' => $notification->public_id,
            'type' => $notification->type->value,
            'type_label' => $notification->type->label(),
            'title' => $notification->title,
            'body' => $notification->body,
            'data' => $notification->data ?? [],
            'read_at' => $notification->read_at?->timezone('Europe/Berlin')->toIso8601String(),
            'unread' => $notification->isUnread(),
            'actor' => $notification->actor ? [
                'id' => $notification->actor->public_id,
                'name' => $notification->actor->name,
            ] : null,
            'created_at' => $notification->created_at?->timezone('Europe/Berlin')->toIso8601String(),
        ];
    }
}
