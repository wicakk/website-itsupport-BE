<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            // ── Admin ──────────────────────────────────────────────────────────
            [
                'name'       => 'Super Admin',
                'email'      => 'admin@company.com',
                'password'   => Hash::make('password'),
                'role'       => 'super_admin',
                'department' => 'IT',
                'initials'   => 'SA',
                'color'      => '#EF4444',
                'is_active'  => true,
            ],
            // ── Manager ─────────────────────────────────────────────────────────
            [
                'name'       => 'Manager Departemen',
                'email'      => 'manager@company.com',
                'password'   => Hash::make('password'),
                'role'       => 'manager_it',
                'department' => 'IT',
                'initials'   => 'MI',
                'color'      => '#F97316',
                'is_active'  => true,
            ],
            // ── IT Support ───────────────────────────────────────────────────────
            [
                'name'       => 'Rizky Ardianto',
                'email'      => 'rizky@company.com',
                'password'   => Hash::make('password'),
                'role'       => 'it_support',
                'department' => 'IT',
                'initials'   => 'RA',
                'color'      => '#3B8BFF',
                'is_active'  => true,
            ],
            [
                'name'       => 'Dian Fitriana',
                'email'      => 'dian@company.com',
                'password'   => Hash::make('password'),
                'role'       => 'it_support',
                'department' => 'IT',
                'initials'   => 'DF',
                'color'      => '#8B5CF6',
                'is_active'  => true,
            ],
            [
                'name'       => 'Ahmad Ridwan',
                'email'      => 'ahmad@company.com',
                'password'   => Hash::make('password'),
                'role'       => 'it_support',
                'department' => 'IT',
                'initials'   => 'AR',
                'color'      => '#06B6D4',
                'is_active'  => true,
            ],
            // ── Regular Users ─────────────────────────────────────────────────────
            [
                'name'       => 'Budi Santoso',
                'email'      => 'budi@company.com',
                'password'   => Hash::make('password'),
                'role'       => 'user',
                'department' => 'Finance',
                'initials'   => 'BS',
                'color'      => '#10B981',
                'is_active'  => true,
            ],
            [
                'name'       => 'Siti Rahayu',
                'email'      => 'siti@company.com',
                'password'   => Hash::make('password'),
                'role'       => 'user',
                'department' => 'HR',
                'initials'   => 'SR',
                'color'      => '#F59E0B',
                'is_active'  => true,
            ],
            [
                'name'       => 'Eko Prasetyo',
                'email'      => 'eko@company.com',
                'password'   => Hash::make('password'),
                'role'       => 'user',
                'department' => 'Marketing',
                'initials'   => 'EP',
                'color'      => '#06B6D4',
                'is_active'  => true,
            ],
        ];

        foreach ($users as $userData) {
            User::firstOrCreate(['email' => $userData['email']], $userData);
        }

        $this->command->info('✅ Users seeded (' . count($users) . ' users)');
    }
}
