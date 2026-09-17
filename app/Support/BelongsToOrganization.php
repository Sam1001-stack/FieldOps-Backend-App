<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Global scope + auto-fill organization_id from the current tenant binding.
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $builder): void {
            $id = app()->bound('current_organization_id')
                ? app('current_organization_id')
                : null;

            if ($id) {
                $builder->where($builder->getModel()->getTable().'.organization_id', $id);
            }
        });

        static::creating(function (Model $model): void {
            if (empty($model->organization_id) && app()->bound('current_organization_id')) {
                $model->organization_id = app('current_organization_id');
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Organization\Organization::class);
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where($this->getTable().'.organization_id', $organizationId);
    }
}
