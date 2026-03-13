<?php

namespace Database\Seeders;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Database\Seeder;

class TicketSeeder extends Seeder
{
    public function run(): void
    {
        $rizky  = User::where('email', 'rizky@company.com')->first();
        $dian   = User::where('email', 'dian@company.com')->first();
        $ahmad  = User::where('email', 'ahmad@company.com')->first();
        $budi   = User::where('email', 'budi@company.com')->first();
        $siti   = User::where('email', 'siti@company.com')->first();
        $eko    = User::where('email', 'eko@company.com')->first();

        $tickets = [
            ['title' => 'Laptop tidak bisa connect WiFi',             'category' => 'Network',  'priority' => 'High',     'status' => 'In Progress',  'requester' => $budi,  'assignee' => $rizky],
            ['title' => 'Email tidak bisa kirim attachment',          'category' => 'Email',    'priority' => 'Medium',   'status' => 'Open',         'requester' => $siti,  'assignee' => null],
            ['title' => 'Printer offline di lantai 3',                'category' => 'Printer',  'priority' => 'Low',      'status' => 'Assigned',     'requester' => $eko,   'assignee' => $dian],
            ['title' => 'Server aplikasi ERP down',                   'category' => 'Server',   'priority' => 'Critical', 'status' => 'In Progress',  'requester' => $budi,  'assignee' => $rizky],
            ['title' => 'Blue screen saat startup Windows',           'category' => 'Hardware', 'priority' => 'High',     'status' => 'Resolved',     'requester' => $siti,  'assignee' => $dian],
            ['title' => 'VPN tidak bisa konek dari rumah',            'category' => 'Network',  'priority' => 'Medium',   'status' => 'Waiting User', 'requester' => $eko,   'assignee' => $ahmad],
            ['title' => 'Software akuntansi crash saat dibuka',       'category' => 'Software', 'priority' => 'High',     'status' => 'Closed',       'requester' => $budi,  'assignee' => $ahmad],
            ['title' => 'Akun email terkunci setelah ganti password', 'category' => 'Security', 'priority' => 'High',     'status' => 'Resolved',     'requester' => $siti,  'assignee' => $rizky],
        ];

        foreach ($tickets as $data) {
            $ticket = Ticket::create([
                'title'        => $data['title'],
                'description'  => 'Deskripsi detail untuk tiket: ' . $data['title'],
                'category'     => $data['category'],
                'priority'     => $data['priority'],
                'status'       => $data['status'],
                'requester_id' => $data['requester']->id,
                'assigned_to'  => $data['assignee']?->id,
                'department'   => $data['requester']->department,
            ]);

            // Add sample comments
            TicketComment::create([
                'ticket_id'   => $ticket->id,
                'user_id'     => $data['requester']->id,
                'body'        => "Mohon segera ditindaklanjuti. Masalah ini mengganggu pekerjaan saya.",
                'is_internal' => false,
            ]);

            if ($data['assignee']) {
                TicketComment::create([
                    'ticket_id'   => $ticket->id,
                    'user_id'     => $data['assignee']->id,
                    'body'        => "Sudah dicek, sedang dalam proses penanganan.",
                    'is_internal' => false,
                ]);
            }
        }

        $this->command->info('✅ Tickets seeded (' . count($tickets) . ' tickets)');
    }
}
