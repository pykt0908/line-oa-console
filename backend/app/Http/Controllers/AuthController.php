<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password) || ! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['อีเมลหรือรหัสผ่านไม่ถูกต้อง'],
            ]);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $this->audit->log('auth.login', $user, null, $user->id);

        return response()->json(['user' => $this->userPayload($user)]);
    }

    public function logout(Request $request)
    {
        $this->audit->log('auth.logout', $request->user());

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'logged out']);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    private function userPayload(User $user): array
    {
        $user->load(['roles:id,name', 'lineAccounts:id,name,slug,account_color,uuid,is_active']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'display_name' => $user->display_name,
            'position' => $user->position,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'line_accounts' => $user->hasAnyRole(['Super Admin', 'Admin'])
                ? \App\Models\LineAccount::query()->get(['id', 'name', 'slug', 'account_color', 'uuid', 'is_active'])
                : $user->lineAccounts,
        ];
    }
}
