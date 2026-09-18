<?php

namespace App\Http\Api\V1;

use App\Application\Identity\AttachMember;
use App\Domain\CRM\Site;
use App\Domain\Identity\User;
use App\Domain\Organization\Organization;
use App\Enums\UserRole;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Login, customer register, logout, GET /me.
 * Register creates a pending customer; office must verify before job create.
 */
class AuthController
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($data)) {
            throw ValidationException::withMessages([
                'email' => ['Anmeldung fehlgeschlagen.'],
            ]);
        }

        $user = $request->user();
        $token = $user->createToken('fieldops')->plainTextToken;

        return [
            'token' => $token,
            'user' => new UserResource($user->load(['currentOrganization', 'customerProfile'])),
        ];
    }

    public function register(Request $request, AttachMember $attach)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:40'],
            'street' => ['required', 'string', 'max:180'],
            'zip' => ['required', 'string', 'max:16'],
            'city' => ['required', 'string', 'max:80'],
            'organization_id' => ['required', 'uuid'],
        ]);

        $org = Organization::query()
            ->where('public_id', $data['organization_id'])
            ->where('suspended', false)
            ->first();

        abort_unless($org, 422, 'Handwerksbetrieb nicht gefunden oder gesperrt.');

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'current_organization_id' => $org->id,
        ]);

        $attach->handle($org, $user, UserRole::Customer);

        $customer = $user->customerProfile()->first();
        if ($customer) {
            Site::query()->create([
                'organization_id' => $org->id,
                'customer_id' => $customer->id,
                'street' => $data['street'],
                'zip' => $data['zip'],
                'city' => $data['city'],
            ]);
        }

        $token = $user->createToken('fieldops')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user->load(['currentOrganization', 'customerProfile'])),
            'message' => 'Konto angelegt. Das Büro bestätigt Sie in Kürze.',
        ], 201);
    }

    public function registerSuperAdmin(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'name' => ['nullable', 'string', 'max:120'],
        ]);

        abort_unless(
            UserRole::from($data['role']) === UserRole::SuperAdmin,
            422,
            'Nur die Rolle super_admin ist erlaubt.',
        );

        $actor = Auth::guard('sanctum')->user();
        $hasSuperAdmin = User::query()->where('is_super_admin', true)->exists();

        if ($hasSuperAdmin && ! $actor?->is_super_admin) {
            abort(403, 'Ein Super-Admin existiert bereits. Melden Sie sich an, um weitere anzulegen.');
        }

        $existing = User::query()->where('email', $data['email'])->first();
        if ($existing && ! $existing->is_super_admin) {
            abort(422, 'E-Mail bereits vergeben.');
        }

        $user = User::query()->updateOrCreate(
            ['email' => $data['email']],
            [
                'name' => $data['name'] ?? 'FieldOps Admin',
                'password' => $data['password'],
                'is_super_admin' => true,
            ],
        );

        $token = $user->createToken('fieldops')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user->load(['currentOrganization', 'customerProfile'])),
            'message' => 'Super-Admin angelegt.',
        ], 201);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Abgemeldet.']);
    }

    public function me(Request $request)
    {
        return new UserResource($request->user()->load(['currentOrganization', 'customerProfile']));
    }
}
