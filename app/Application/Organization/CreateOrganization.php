<?php

namespace App\Application\Organization;

use App\Application\Identity\AttachMember;
use App\Domain\Catalog\CatalogItem;
use App\Domain\Identity\User;
use App\Domain\Organization\Organization;
use App\Domain\Organization\OrganizationSetting;
use App\Enums\UserRole;
use Illuminate\Support\Facades\DB;

final class CreateOrganization
{
    public function handle(User $actor, array $data): Organization
    {
        abort_unless($actor->is_super_admin, 403, 'Nur der Super-Admin darf Mandanten anlegen.');

        return DB::transaction(function () use ($actor, $data): Organization {
            $org = Organization::query()->create([
                'name' => $data['name'],
                'plan' => 'trial',
                'trial_ends_at' => now()->addDays(14),
            ]);

            OrganizationSetting::query()->create([
                'organization_id' => $org->id,
                'legal_name' => $data['name'],
                'street' => $data['street'] ?? null,
                'zip' => $data['zip'] ?? null,
                'city' => $data['city'] ?? null,
                'invoice_prefix' => 'RE',
                'next_invoice_number' => 1,
            ]);

            CatalogItem::query()->create([
                'organization_id' => $org->id,
                'kind' => 'labor',
                'name' => 'Monteurstunde',
                'unit' => 'h',
                'unit_price_cents' => 8900,
                'tax_rate' => 19,
                'is_hourly_rate' => true,
            ]);

            $owner = User::query()->create([
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'password' => $data['owner_password'],
                'current_organization_id' => $org->id,
            ]);

            app(AttachMember::class)->handle($org, $owner, UserRole::Owner, $actor);

            $actor->forceFill(['current_organization_id' => $org->id])->save();
            app()->instance('current_organization_id', $org->id);
            setPermissionsTeamId($org->id);

            activity()->causedBy($actor)->performedOn($org)->log('org.created');

            return $org->load(['settings', 'users']);
        });
    }
}
