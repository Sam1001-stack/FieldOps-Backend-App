<?php

namespace App\Domain\Notifications;

use App\Domain\Identity\User;
use App\Domain\Organization\Organization;
use App\Enums\NotificationType;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    use HasPublicUuid;

    protected $table = 'app_notifications';

    protected $fillable = [
        'organization_id', 'user_id', 'actor_id', 'type', 'title', 'body', 'data', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }
}
