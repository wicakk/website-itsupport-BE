<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Buat Permissions ──────────────────────────────────────────
        $permissions = [
            // Users
            ['name' => 'users.view',   'display_name' => 'Lihat Users',   'group' => 'users'],
            ['name' => 'users.create', 'display_name' => 'Buat User',     'group' => 'users'],
            ['name' => 'users.edit',   'display_name' => 'Edit User',     'group' => 'users'],
            ['name' => 'users.delete', 'display_name' => 'Hapus User',    'group' => 'users'],

            // Roles
            ['name' => 'roles.view',   'display_name' => 'Lihat Roles',   'group' => 'roles'],
            ['name' => 'roles.create', 'display_name' => 'Buat Role',     'group' => 'roles'],
            ['name' => 'roles.edit',   'display_name' => 'Edit Role',     'group' => 'roles'],
            ['name' => 'roles.delete', 'display_name' => 'Hapus Role',    'group' => 'roles'],

            // Reports (contoh module lain)
            ['name' => 'reports.view',   'display_name' => 'Lihat Laporan',   'group' => 'reports'],
            ['name' => 'reports.export', 'display_name' => 'Export Laporan',  'group' => 'reports'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm['name']], $perm);
        }

        // ── 2. Buat Roles & assign permissions ───────────────────────────
        $allPermissionIds = Permission::pluck('id')->toArray();

        // Super Admin — semua permission
        $superAdmin = Role::firstOrCreate(
            ['name' => 'super-admin'],
            ['display_name' => 'Super Admin', 'description' => 'Akses penuh ke seluruh sistem']
        );
        $superAdmin->syncPermissions($allPermissionIds);

        // Admin — semua kecuali hapus user & role
        $admin = Role::firstOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Admin', 'description' => 'Manajemen user dan role']
        );
        $adminPerms = Permission::whereNotIn('name', ['users.delete', 'roles.delete'])->pluck('id')->toArray();
        $admin->syncPermissions($adminPerms);

        // Staff — hanya view
        $staff = Role::firstOrCreate(
            ['name' => 'staff'],
            ['display_name' => 'Staff', 'description' => 'Akses baca saja']
        );
        $staffPerms = Permission::where('name', 'LIKE', '%.view')->pluck('id')->toArray();
        $staff->syncPermissions($staffPerms);

        // ── 3. (Opsional) Assign super-admin ke user pertama ────────────
        $firstUser = User::first();
        if ($firstUser) {
            $firstUser->syncRoles([$superAdmin->id]);
            $this->command->info("Super Admin assigned to: {$firstUser->email}");
        }

        $this->command->info('Roles & Permissions seeded successfully!');
    }
}
