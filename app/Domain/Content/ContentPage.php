<?php

namespace App\Domain\Content;

use App\Domain\Identity\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentPage extends Model
{
    protected $fillable = [
        'slug', 'title', 'body', 'audience', 'sort_order', 'published', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'published' => 'boolean',
        ];
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function visibleTo(?string $audience): bool
    {
        if (! $this->published) {
            return false;
        }
        if (! $audience || $audience === 'all') {
            return true;
        }

        return in_array($this->audience, ['all', $audience], true);
    }
}
