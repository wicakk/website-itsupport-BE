<?php
// database/seeders/ProjectSeeder.php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskColumn;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $manager = User::where('role', 'manager_it')->first()
            ?? User::where('role', 'super_admin')->first();
        $support = User::where('role', 'it_support')->first();
        $users   = User::all();

        if (!$manager) return;

        $projects = [
            [
                'name'        => 'Migrasi Server 2025',
                'description' => 'Upgrade infrastruktur server ke cloud-based architecture.',
                'color'       => '#6366f1',
                'status'      => 'active',
                'due_date'    => now()->addMonths(2),
            ],
            [
                'name'        => 'Implementasi ERP',
                'description' => 'Rollout sistem ERP baru ke seluruh departemen.',
                'color'       => '#10B981',
                'status'      => 'active',
                'due_date'    => now()->addMonths(4),
            ],
            [
                'name'        => 'Security Audit Q1',
                'description' => 'Audit keamanan sistem dan patch vulnerabilities.',
                'color'       => '#F59E0B',
                'status'      => 'on_hold',
                'due_date'    => now()->addMonth(),
            ],
        ];

        foreach ($projects as $pData) {
            $project = Project::create([...$pData, 'created_by' => $manager->id]);

            // Default columns
            $cols = [
                ['name' => 'To Do',      'color' => '#94A3B8', 'position' => 0],
                ['name' => 'In Progress','color' => '#6366f1', 'position' => 1],
                ['name' => 'Review',     'color' => '#F59E0B', 'position' => 2],
                ['name' => 'Done',       'color' => '#10B981', 'position' => 3],
            ];
            $createdCols = [];
            foreach ($cols as $c) {
                $createdCols[] = TaskColumn::create(['project_id' => $project->id, ...$c]);
            }

            // Members
            $project->members()->attach($manager->id, ['role' => 'owner']);
            if ($support) $project->members()->attach($support->id, ['role' => 'member']);

            // Sample tasks
            $sampleTasks = [
                ['title' => 'Analisis kebutuhan awal',    'col' => 0, 'priority' => 'high',   'done' => true],
                ['title' => 'Setup environment staging',  'col' => 1, 'priority' => 'high',   'done' => false],
                ['title' => 'Dokumentasi teknis',         'col' => 1, 'priority' => 'medium', 'done' => false],
                ['title' => 'Testing & QA',               'col' => 2, 'priority' => 'medium', 'done' => false],
                ['title' => 'Review desain arsitektur',   'col' => 3, 'priority' => 'low',    'done' => true],
            ];

            foreach ($sampleTasks as $i => $t) {
                $colIndex = $t['done'] ? 3 : $t['col'];
                Task::create([
                    'project_id'  => $project->id,
                    'column_id'   => $createdCols[$colIndex]->id,
                    'title'       => $t['title'],
                    'priority'    => $t['priority'],
                    'created_by'  => $manager->id,
                    'assigned_to' => $support?->id,
                    'position'    => $i,
                    'due_date'    => now()->addDays(rand(3, 14)),
                ]);
            }
        }

        $this->command->info('✅ Projects seeded!');
    }
}
