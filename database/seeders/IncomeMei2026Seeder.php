<?php

namespace Database\Seeders;

use App\Models\Income;
use Illuminate\Database\Seeder;

class IncomeMei2026Seeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['2026-05-03', 'Ronald', 'Setoran kas Ronald', 2000000],
            ['2026-05-08', 'Ronald', 'Setoran kas Ronald', 450000],
            ['2026-05-08', 'Ronald', 'Setoran kas Ronald', 1000000],
            ['2026-05-08', 'Ronald', 'Setoran kas Ronald', 1500000],
            ['2026-05-13', 'Ronald', 'Setoran kas Ronald', 500000],
            ['2026-05-21', 'Ronald', 'Setoran kas Ronald', 200000],
            ['2026-05-22', 'Ronald', 'Setoran kas Ronald', 1500000],
            ['2026-05-24', 'Ronald', 'Setoran kas Ronald', 1000000],
        ];

        foreach ($data as [$date, $source, $description, $amount]) {
            Income::create([
                'income_date'    => $date,
                'source'         => $source,
                'description'    => $description,
                'amount'         => $amount,
                'recorded_by_id' => null,
            ]);
        }
    }
}
