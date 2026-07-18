<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * CRUD des "profils" (rôles Spatie utilisés comme paquets de permissions nommés,
 * entièrement définis par l'admin — aucun profil n'est câblé en dur dans le code).
 */
class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::withCount(['users', 'permissions'])->orderBy('name')->get();

        return view('roles.index', compact('roles'));
    }

    public function create()
    {
        return view('roles.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $role = DB::transaction(function () use ($data) {
            $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
            $role->syncPermissions($data['permissions'] ?? []);

            AuditLog::record('role.created', $role, [], ['name' => $role->name, 'permissions' => $data['permissions'] ?? []], auth()->id());

            return $role;
        });

        return redirect()->route('roles.index')->with('success', 'Profil « '.$role->name.' » créé.');
    }

    public function edit(Role $role)
    {
        $role->load('permissions');

        return view('roles.edit', compact('role'));
    }

    public function update(Request $request, Role $role)
    {
        $data = $this->validated($request, $role);
        $oldPermissions = $role->permissions->pluck('name')->sort()->values()->all();
        $oldName = $role->name;
        $countBefore = User::countActiveUsersWithPermission('utilisateurs.permissions');

        try {
            DB::transaction(function () use ($data, $role, $countBefore) {
                $role->update(['name' => $data['name']]);
                $role->syncPermissions($data['permissions'] ?? []);

                User::assertAtLeastOnePermissionManagerRemains($countBefore);
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $newPermissions = $role->fresh('permissions')->permissions->pluck('name')->sort()->values()->all();

        if ($oldPermissions !== $newPermissions || $oldName !== $data['name']) {
            AuditLog::record('role.updated', $role, [
                'name' => $oldName, 'permissions' => $oldPermissions,
            ], [
                'name' => $data['name'], 'permissions' => $newPermissions,
            ], $request->user()->id);
        }

        return redirect()->route('roles.index')->with('success', 'Profil mis à jour.');
    }

    public function destroy(Request $request, Role $role)
    {
        $countBefore = User::countActiveUsersWithPermission('utilisateurs.permissions');

        try {
            DB::transaction(function () use ($role, $request, $countBefore) {
                AuditLog::record('role.deleted', $role, ['name' => $role->name], [], $request->user()->id);
                $role->delete();
                User::assertAtLeastOnePermissionManagerRemains($countBefore);
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('roles.index')->with('success', 'Profil supprimé.');
    }

    /** @return array{name: string, permissions: array<int, string>} */
    private function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role?->id)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists((new Permission())->getTable(), 'name')->where('guard_name', 'web')],
        ]);
    }
}
