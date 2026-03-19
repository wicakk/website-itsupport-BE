<?php
// database/seeders/RolePermissionSeeder.php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Permissions ───────────────────────────────────────────────
        $permissions = [
            // Dashboard
            ['name' => 'dashboard.view',     'display_name' => 'Lihat Dashboard',          'group' => 'dashboard'],
            ['name' => 'dashboard.charts',   'display_name' => 'Lihat Grafik & Statistik', 'group' => 'dashboard'],
            // Tiket
            ['name' => 'tickets.view',       'display_name' => 'Lihat Semua Tiket',        'group' => 'tickets'],
            ['name' => 'tickets.create',     'display_name' => 'Buat Tiket',               'group' => 'tickets'],
            ['name' => 'tickets.edit',       'display_name' => 'Edit Tiket',               'group' => 'tickets'],
            ['name' => 'tickets.delete',     'display_name' => 'Hapus Tiket',              'group' => 'tickets'],
            ['name' => 'tickets.assign',     'display_name' => 'Assign Tiket ke Teknisi',  'group' => 'tickets'],
            ['name' => 'tickets.resolve',    'display_name' => 'Resolve / Close Tiket',    'group' => 'tickets'],
            ['name' => 'tickets.comment',    'display_name' => 'Tambah Komentar',          'group' => 'tickets'],
            // Aset
            ['name' => 'assets.view',        'display_name' => 'Lihat Aset',               'group' => 'assets'],
            ['name' => 'assets.create',      'display_name' => 'Tambah Aset',              'group' => 'assets'],
            ['name' => 'assets.edit',        'display_name' => 'Edit Aset',                'group' => 'assets'],
            ['name' => 'assets.delete',      'display_name' => 'Hapus Aset',               'group' => 'assets'],
            ['name' => 'assets.assign',      'display_name' => 'Assign Aset ke User',      'group' => 'assets'],
            ['name' => 'assets.maintain',    'display_name' => 'Set Status Maintenance',   'group' => 'assets'],
            // Knowledge Base
            ['name' => 'knowledge.view',     'display_name' => 'Lihat Artikel',            'group' => 'knowledge'],
            ['name' => 'knowledge.create',   'display_name' => 'Tulis Artikel',            'group' => 'knowledge'],
            ['name' => 'knowledge.edit',     'display_name' => 'Edit Artikel',             'group' => 'knowledge'],
            ['name' => 'knowledge.delete',   'display_name' => 'Hapus Artikel',            'group' => 'knowledge'],
            // Monitoring
            ['name' => 'monitoring.view',    'display_name' => 'Lihat Server Monitor',     'group' => 'monitoring'],
            ['name' => 'monitoring.manage',  'display_name' => 'Tambah / Hapus Server',    'group' => 'monitoring'],
            ['name' => 'monitoring.ping',    'display_name' => 'Ping Server',              'group' => 'monitoring'],
            // Reports
            ['name' => 'reports.view',       'display_name' => 'Lihat Laporan',            'group' => 'reports'],
            ['name' => 'reports.export',     'display_name' => 'Export Laporan',           'group' => 'reports'],
            // Users
            ['name' => 'users.view',         'display_name' => 'Lihat Daftar User',        'group' => 'users'],
            ['name' => 'users.create',       'display_name' => 'Tambah User',              'group' => 'users'],
            ['name' => 'users.edit',         'display_name' => 'Edit User',                'group' => 'users'],
            ['name' => 'users.delete',       'display_name' => 'Hapus User',               'group' => 'users'],
            // Roles
            ['name' => 'roles.view',         'display_name' => 'Lihat Role & Permissions', 'group' => 'roles'],
            ['name' => 'roles.edit',         'display_name' => 'Edit Permissions Role',    'group' => 'roles'],
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p['name']], $p);
        }

        // ── 2. Roles + assign permissions ───────────────────────────────
        $all = Permission::pluck('id')->toArray();

        // Super Admin — semua permission
        $superAdmin = Role::firstOrCreate(
            ['name' => 'super_admin'],
            ['display_name' => 'Super Admin', 'description' => 'Akses penuh ke seluruh sistem']
        );
        $superAdmin->permissions()->sync($all);

        // Manager IT
        $managerIT = Role::firstOrCreate(
            ['name' => 'manager_it'],
            ['display_name' => 'Manager IT', 'description' => 'Manajemen tim IT dan laporan']
        );
        $managerIT->permissions()->sync(
            Permission::whereNotIn('name', ['tickets.delete', 'assets.delete', 'users.delete', 'roles.edit'])->pluck('id')
        );

        // IT Support
        $itSupport = Role::firstOrCreate(
            ['name' => 'it_support'],
            ['display_name' => 'IT Support', 'description' => 'Menangani tiket dan aset']
        );
        $itSupport->permissions()->sync(
            Permission::whereIn('name', [
                'dashboard.view', 'dashboard.charts',
                'tickets.view', 'tickets.create', 'tickets.edit', 'tickets.resolve', 'tickets.comment',
                'assets.view', 'assets.edit', 'assets.maintain',
                'knowledge.view', 'knowledge.create', 'knowledge.edit',
                'monitoring.view', 'monitoring.ping',
                'reports.view',
                'users.view',
            ])->pluck('id')
        );

        // User biasa
        $user = Role::firstOrCreate(
            ['name' => 'user'],
            ['display_name' => 'User', 'description' => 'Akses dasar sistem']
        );
        $user->permissions()->sync(
            Permission::whereIn('name', [
                'dashboard.view',
                'tickets.view', 'tickets.create', 'tickets.comment',
                'assets.view',
                'knowledge.view',
            ])->pluck('id')
        );

        $this->command->info('✅ Roles & Permissions seeded!');
    }
}
