<?php

namespace Database\Seeders;

use App\Models\BudgetCategory;
use App\Models\BudgetItem;
use Illuminate\Database\Seeder;

class BudgetSeeder extends Seeder
{
    public function run(): void
    {
        $fixed = BudgetCategory::create(['name' => 'Fixed Cost', 'total_budget' => 6995000]);
        $variable = BudgetCategory::create(['name' => 'Variable Cost Internal', 'total_budget' => 6000000]);
        $kas = BudgetCategory::create(['name' => 'Dana Kas', 'total_budget' => 2000000]);

        $fixedItems = [
            ['name' => 'Server Database',    'unit_cost' => 580000,  'rate' => 'bulanan',  'multiplier' => 1],
            ['name' => 'Server Aplikasi',    'unit_cost' => 580000,  'rate' => 'bulanan',  'multiplier' => 1],
            ['name' => 'Claude Wilson',      'unit_cost' => 355000,  'rate' => 'bulanan',  'multiplier' => 1],
            ['name' => 'Claude Ridwan',      'unit_cost' => 355000,  'rate' => 'bulanan',  'multiplier' => 1],
            ['name' => 'Wifi',               'unit_cost' => 0,       'rate' => 'bulanan',  'multiplier' => 1],
            ['name' => 'Listrik',            'unit_cost' => 2000000, 'rate' => 'bulanan',  'multiplier' => 1],
            ['name' => 'Air',                'unit_cost' => 0,       'rate' => 'bulanan',  'multiplier' => 1],
            ['name' => 'Afuk',               'unit_cost' => 20000,   'rate' => 'harian',   'multiplier' => 30],
            ['name' => 'Kartu Parkir',       'unit_cost' => 150000,  'rate' => 'custom',   'multiplier' => 3],
            ['name' => 'Gaji Wilson',        'unit_cost' => 2000000, 'rate' => 'bulanan',  'multiplier' => 1],
            ['name' => 'Biaya Server Ruko',  'unit_cost' => 75000,   'rate' => 'bulanan',  'multiplier' => 1],
        ];

        foreach ($fixedItems as $item) {
            BudgetItem::create(array_merge($item, ['category_id' => $fixed->id]));
        }

        $variableItems = [
            ['name' => 'Beras 10 kg',               'unit_cost' => 76000,  'rate' => 'dua_mingguan', 'multiplier' => 2],
            ['name' => 'Telur',                      'unit_cost' => 30000,  'rate' => 'mingguan',     'multiplier' => 4],
            ['name' => 'Gula',                       'unit_cost' => 30000,  'rate' => 'dua_mingguan', 'multiplier' => 2],
            ['name' => 'Kopi',                       'unit_cost' => 240000, 'rate' => 'bulanan',      'multiplier' => 1],
            ['name' => 'Air Galon',                  'unit_cost' => 5000,   'rate' => 'custom',       'multiplier' => 24],
            ['name' => 'LPG',                        'unit_cost' => 28000,  'rate' => 'bulanan',      'multiplier' => 1],
            ['name' => 'Laundry',                    'unit_cost' => 150000, 'rate' => 'mingguan',     'multiplier' => 4],
            ['name' => 'Makan Internal',             'unit_cost' => 150000, 'rate' => 'harian',       'multiplier' => 30],
            ['name' => 'Sabun + Pasta Gigi + Minyak','unit_cost' => 180000, 'rate' => 'bulanan',      'multiplier' => 1],
        ];

        foreach ($variableItems as $item) {
            BudgetItem::create(array_merge($item, ['category_id' => $variable->id]));
        }

        BudgetItem::create([
            'category_id' => $kas->id,
            'name'        => 'Dana Kas',
            'unit_cost'   => 500000,
            'rate'        => 'mingguan',
            'multiplier'  => 4,
        ]);
    }
}
