<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Seeder;

class AssetSeeder extends Seeder
{
    public function run(): void
    {
        $budi  = User::where('email', 'budi@company.com')->first();
        $rizky = User::where('email', 'rizky@company.com')->first();

        $assets = [
            ['name' => 'Dell Latitude 5520',        'category' => 'Laptop',  'brand' => 'Dell',   'serial_number' => 'DL5520-001', 'status' => 'Active',      'location' => 'Lantai 2 - Finance',      'purchase_date' => '2022-03-15', 'warranty_expiry' => '2025-03-15', 'assigned_to' => $budi->id,  'specs' => ['ram' => '16GB', 'cpu' => 'Intel i7', 'storage' => '512GB SSD']],
            ['name' => 'HP LaserJet Pro M404n',     'category' => 'Printer', 'brand' => 'HP',     'serial_number' => 'HP-LJ-0042', 'status' => 'Maintenance', 'location' => 'Lantai 3 - Meeting Room', 'purchase_date' => '2021-08-20', 'warranty_expiry' => '2024-08-20', 'assigned_to' => null,       'specs' => []],
            ['name' => 'Cisco Catalyst 2960',       'category' => 'Network', 'brand' => 'Cisco',  'serial_number' => 'CS-2960-003','status' => 'Active',      'location' => 'Server Room',             'purchase_date' => '2020-01-10', 'warranty_expiry' => '2025-01-10', 'assigned_to' => null,       'specs' => ['ports' => 48]],
            ['name' => 'Lenovo ThinkPad X1 Carbon', 'category' => 'Laptop',  'brand' => 'Lenovo', 'serial_number' => 'LN-X1-0089', 'status' => 'Active',      'location' => 'Lantai 1 - IT',           'purchase_date' => '2023-05-01', 'warranty_expiry' => '2026-05-01', 'assigned_to' => $rizky->id, 'specs' => ['ram' => '32GB', 'cpu' => 'Intel i9', 'storage' => '1TB SSD']],
            ['name' => 'Dell PowerEdge R740',       'category' => 'Server',  'brand' => 'Dell',   'serial_number' => 'PE-R740-001','status' => 'Active',      'location' => 'Data Center',             'purchase_date' => '2021-11-30', 'warranty_expiry' => '2026-11-30', 'assigned_to' => null,       'specs' => ['ram' => '128GB', 'cpu' => 'Dual Xeon', 'storage' => '4TB RAID']],
        ];

        foreach ($assets as $data) {
            Asset::create($data);
        }

        $this->command->info('✅ Assets seeded (' . count($assets) . ' assets)');
    }
}
