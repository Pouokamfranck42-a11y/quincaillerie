<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with(['roles', 'permissions'])->orderBy('name')->paginate(20);

        return view('users.index', compact('users'));
    }

    public function create()
    {
        $roles = Role::orderBy('name')->get();

        return view('users.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            if (! empty($data['role'])) {
                $user->assignRole($data['role']);
            }
            if (! empty($data['permissions'])) {
                $user->givePermissionTo($data['permissions']);
            }

            AuditLog::record('user.created', $user, [], [
                'role' => $data['role'] ?? null,
                'permissions' => $data['permissions'] ?? [],
            ], auth()->id());
        });

        return redirect()->route('users.index')->with('success', 'Utilisateur créé.');
    }

    public function edit(User $user)
    {
        $roles = Role::orderBy('name')->get();
        $user->load(['roles', 'permissions']);

        return view('users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);

        $oldPermissions = $user->getAllPermissions()->pluck('name')->sort()->values()->all();
        $oldRole = $user->roles->pluck('name')->first();

        $countBefore = User::countActiveUsersWithPermission('utilisateurs.permissions');

        try {
            DB::transaction(function () use ($data, $user, $countBefore) {
                $user->update([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    ...(! empty($data['password']) ? ['password' => $data['password']] : []),
                ]);

                $user->syncRoles(! empty($data['role']) ? [$data['role']] : []);
                $user->syncPermissions($data['permissions'] ?? []);

                User::assertAtLeastOnePermissionManagerRemains($countBefore);
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $newPermissions = $user->fresh()->getAllPermissions()->pluck('name')->sort()->values()->all();
        $newRole = $data['role'] ?? null;

        if ($oldPermissions !== $newPermissions || $oldRole !== $newRole) {
            AuditLog::record('user.permissions_changed', $user, [
                'role' => $oldRole, 'permissions' => $oldPermissions,
            ], [
                'role' => $newRole, 'permissions' => $newPermissions,
            ], $request->user()->id);
        }

        return redirect()->route('users.index')->with('success', 'Utilisateur mis à jour.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Vous ne pouvez pas supprimer votre propre compte.');
        }

        $countBefore = User::countActiveUsersWithPermission('utilisateurs.permissions');

        try {
            DB::transaction(function () use ($user, $countBefore) {
                $user->delete();
                User::assertAtLeastOnePermissionManagerRemains($countBefore);
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('users.index')->with('success', 'Utilisateur envoyé à la corbeille (accès immédiatement révoqué).');
    }

    /** @return array{name: string, email: string, password: ?string, role: ?string, permissions: array<int, string>} */
    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', Password::min(8)],
            'role' => ['nullable', 'string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ]);
    }
}
