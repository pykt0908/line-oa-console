<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index(Request $request)
    {
        abort_unless($request->user()->can('user.manage'), 403);

        $users = User::query()
            ->with(['roles:id,name', 'lineAccounts:id,name'])
            ->orderBy('name')
            ->paginate(50);

        return response()->json($users);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('user.manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
            'line_account_ids' => ['array'],
            'line_account_ids.*' => ['integer', 'exists:line_accounts,id'],
            'is_active' => ['boolean'],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'display_name' => $data['display_name'] ?? $data['name'],
            'position' => $data['position'] ?? null,
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_active' => $data['is_active'] ?? true,
        ]);

        $user->syncRoles([$data['role']]);
        $user->lineAccounts()->sync($data['line_account_ids'] ?? []);

        $this->audit->log('user.create', $user);

        return response()->json(['data' => $user->load(['roles', 'lineAccounts'])], 201);
    }

    public function update(Request $request, User $user)
    {
        abort_unless($request->user()->can('user.manage'), 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['sometimes', 'string', Rule::exists('roles', 'name')],
            'line_account_ids' => ['array'],
            'line_account_ids.*' => ['integer', 'exists:line_accounts,id'],
            'is_active' => ['boolean'],
        ]);

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $role = $data['role'] ?? null;
        $lineAccountIds = $data['line_account_ids'] ?? null;
        unset($data['role'], $data['line_account_ids']);

        $user->update($data);

        if ($role) {
            $user->syncRoles([$role]);
        }
        if (is_array($lineAccountIds)) {
            $user->lineAccounts()->sync($lineAccountIds);
        }

        $this->audit->log('user.update', $user);

        return response()->json(['data' => $user->fresh(['roles', 'lineAccounts'])]);
    }

    public function roles(Request $request)
    {
        abort_unless($request->user()->can('user.manage'), 403);

        return response()->json(['data' => Role::query()->orderBy('name')->pluck('name')]);
    }
}
