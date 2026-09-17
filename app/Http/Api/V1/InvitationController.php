<?php

namespace App\Http\Api\V1;

use App\Application\Identity\AttachMember;
use App\Domain\Identity\Invitation;
use App\Domain\Identity\User;
use App\Domain\Organization\Organization;
use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InvitationController extends Controller
{
    public function store(Request $request)
    {
        $org = $request->user()->currentOrganization;
        $data = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', 'in:owner,office,monteur,customer,accountant'],
        ]);
        $this->authorize('invite', [$org, $data['role']]);

        $invite = Invitation::issue($org, $request->user(), $data['email'], UserRole::from($data['role']));

        return ['id' => $invite->public_id, 'token' => $invite->token, 'email' => $invite->email];
    }

    public function accept(Request $request, AttachMember $attach)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'name' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8'],
        ]);
        $invite = Invitation::query()->withoutGlobalScopes()->where('token', $data['token'])->firstOrFail();
        abort_if($invite->accepted_at || $invite->expires_at->isPast(), 422, 'Einladung ungültig.');

        $user = User::query()->firstOrCreate(
            ['email' => $invite->email],
            ['name' => $data['name'], 'password' => Hash::make($data['password']), 'public_id' => (string) Str::uuid()]
        );
        $org = Organization::query()->findOrFail($invite->organization_id);
        $inviter = User::query()->find($invite->invited_by);
        $attach->handle($org, $user, $invite->role, $inviter);
        $invite->update(['accepted_at' => now()]);

        $token = $user->createToken('fieldops')->plainTextToken;

        return ['token' => $token];
    }
}
