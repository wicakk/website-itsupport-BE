<?php

namespace Database\Seeders;

use App\Models\KnowledgeBase;
use App\Models\User;
use Illuminate\Database\Seeder;

class KnowledgeBaseSeeder extends Seeder
{
    public function run(): void
    {
        $rizky = User::where('email', 'rizky@company.com')->first();
        $dian  = User::where('email', 'dian@company.com')->first();
        $ahmad = User::where('email', 'ahmad@company.com')->first();

        $articles = [
            ['title' => 'Cara Install Driver Printer HP LaserJet',   'category' => 'Printer',  'tags' => ['printer','driver','hp'],        'author' => $rizky, 'rating' => 4.8, 'rating_count' => 21, 'views' => 234],
            ['title' => 'Reset Password Email Outlook 365',          'category' => 'Email',    'tags' => ['email','outlook','password'],    'author' => $dian,  'rating' => 4.5, 'rating_count' => 15, 'views' => 189],
            ['title' => 'Cara Mapping Network Drive Windows 11',     'category' => 'Network',  'tags' => ['network','drive','windows'],     'author' => $ahmad, 'rating' => 4.9, 'rating_count' => 28, 'views' => 312],
            ['title' => 'Troubleshoot Koneksi VPN Cisco AnyConnect', 'category' => 'Network',  'tags' => ['vpn','cisco','remote'],          'author' => $rizky, 'rating' => 4.2, 'rating_count' => 11, 'views' => 145],
            ['title' => 'Backup Data Otomatis ke OneDrive',          'category' => 'Software', 'tags' => ['backup','onedrive','cloud'],     'author' => $dian,  'rating' => 4.7, 'rating_count' => 19, 'views' => 267],
        ];

        foreach ($articles as $data) {
            KnowledgeBase::create([
                'title'        => $data['title'],
                'content'      => "## {$data['title']}\n\nIni adalah panduan lengkap tentang {$data['title']}.\n\n### Langkah-langkah:\n1. Langkah pertama\n2. Langkah kedua\n3. Langkah ketiga\n\n### Catatan\nHubungi IT Support jika masih mengalami masalah.",
                'category'     => $data['category'],
                'tags'         => $data['tags'],
                'author_id'    => $data['author']->id,
                'is_published' => true,
                'rating'       => $data['rating'],
                'rating_count' => $data['rating_count'],
                'views'        => $data['views'],
            ]);
        }

        $this->command->info('✅ Knowledge base seeded (' . count($articles) . ' articles)');
    }
}
