<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $companies = [
            ['name' => 'Hao Hao Trading',      'prefix' => 'INV/HAOHAO',  'variant' => 'modern',  'primary' => '#dc2626', 'secondary' => '#fbbf24'],
            ['name' => 'Sumber Rejeki Jaya',   'prefix' => 'INV/SRJ',     'variant' => 'classic', 'primary' => '#1e3a8a', 'secondary' => '#3b82f6'],
            ['name' => 'Maju Bersama Niaga',   'prefix' => 'INV/MBN',     'variant' => 'minimal', 'primary' => '#0f172a', 'secondary' => '#64748b'],
            ['name' => 'Cahaya Abadi Supply',  'prefix' => 'INV/CAS',     'variant' => 'bold',    'primary' => '#15803d', 'secondary' => '#4ade80'],
            ['name' => 'Mitra Sentosa Grosir', 'prefix' => 'INV/MSG',     'variant' => 'modern',  'primary' => '#7c3aed', 'secondary' => '#a78bfa'],
            ['name' => 'Berkah Mandiri Pratama','prefix' => 'INV/BMP',    'variant' => 'classic', 'primary' => '#b45309', 'secondary' => '#f59e0b'],
            ['name' => 'Surya Kencana Logistik','prefix' => 'INV/SKL',    'variant' => 'minimal', 'primary' => '#be123c', 'secondary' => '#fb7185'],
            ['name' => 'Anugerah Sejahtera',   'prefix' => 'INV/ANS',     'variant' => 'bold',    'primary' => '#0e7490', 'secondary' => '#22d3ee'],
            ['name' => 'Karya Utama Distribusi','prefix' => 'INV/KUD',    'variant' => 'modern',  'primary' => '#4338ca', 'secondary' => '#818cf8'],
            ['name' => 'Prima Jaya Makmur',    'prefix' => 'INV/PJM',     'variant' => 'classic', 'primary' => '#166534', 'secondary' => '#86efac'],
        ];

        foreach ($companies as $i => $c) {
            Company::updateOrCreate(
                ['invoice_prefix' => $c['prefix']],
                [
                    'name'             => $c['name'],
                    'legal_name'       => 'PT ' . $c['name'],
                    'npwp'             => sprintf('%02d.%03d.%03d.%d-%03d.000', rand(10, 99), rand(100, 999), rand(100, 999), rand(1, 9), rand(100, 999)),
                    'address'          => 'Jl. Industri Raya No. ' . ($i + 1) . ', Surabaya',
                    'phone'            => '031-' . rand(1000000, 9999999),
                    'email'            => 'finance@' . strtolower(str_replace(' ', '', $c['name'])) . '.co.id',
                    'bank_name'        => ['BCA', 'Mandiri', 'BNI', 'BRI'][array_rand(['BCA', 'Mandiri', 'BNI', 'BRI'])],
                    'bank_account'     => (string) rand(1000000000, 9999999999),
                    'bank_holder'      => 'PT ' . $c['name'],
                    'brand_primary'    => $c['primary'],
                    'brand_secondary'  => $c['secondary'],
                    'font_family'      => 'Inter',
                    'template_variant' => $c['variant'],
                    'invoice_prefix'   => $c['prefix'],
                ]
            );
        }
    }
}
