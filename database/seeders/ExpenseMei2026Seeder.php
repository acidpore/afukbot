<?php

namespace Database\Seeders;

use App\Models\Expense;
use Illuminate\Database\Seeder;

class ExpenseMei2026Seeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['2026-05-03', 'Afuk',    'Rokok Afuk',                  11200,   null],
            ['2026-05-03', 'Belanja', 'Alfamart',                    137500,  null],
            ['2026-05-03', 'Makan',   'Makan',                       59000,   null],
            ['2026-05-04', 'Belanja', 'Alfamart',                    48900,   null],
            ['2026-05-04', 'Afuk',    'Tembakau Afuk',               10000,   null],
            ['2026-05-04', 'Makan',   'Makan',                       20000,   null],
            ['2026-05-04', 'Makan',   'Makan',                       36200,   null],
            ['2026-05-04', 'Go MBG',  'Transfer Ridwan',             300000,  null],
            ['2026-05-04', 'Makan',   'Makan Sei',                   125000,  null],
            ['2026-05-05', 'Lainnya', 'Rokok Ronald',                95200,   null],
            ['2026-05-05', 'Lainnya', 'E-Toll',                      101000,  null],
            ['2026-05-05', 'Belanja', 'Aqua',                        9000,    null],
            ['2026-05-05', 'Afuk',    'Makan Afuk',                  19400,   null],
            ['2026-05-05', 'Afuk',    'Uang Afuk',                   50000,   null],
            ['2026-05-05', 'Go MBG',  'Transfer Ridwan',             100000,  null],
            ['2026-05-06', 'Lainnya', 'Alfamart + Rokok Ronald',     226000,  null],
            ['2026-05-06', 'Afuk',    'Uang Afuk',                   50000,   null],
            ['2026-05-07', 'Belanja', 'Alfamart',                    19800,   null],
            ['2026-05-07', 'Makan',   'Makan',                       69000,   null],
            ['2026-05-08', 'Go MBG',  'Transfer Ridwan',             1000000, null],
            ['2026-05-08', 'Go MBG',  'Transfer Ridwan',             200000,  null],
            ['2026-05-08', 'Makan',   'Beli Makan',                  36700,   null],
            ['2026-05-08', 'Belanja', 'Beli Nampan',                 1045000, null],
            ['2026-05-09', 'Makan',   'Beli Makan',                  44000,   null],
            ['2026-05-09', 'Makan',   'Beli Makan',                  21000,   null],
            ['2026-05-09', 'Makan',   'Minum Kuli',                  15000,   null],
            ['2026-05-10', 'Afuk',    'Uang Afuk',                   100000,  null],
            ['2026-05-10', 'Go MBG',  'Claude',                      370000,  null],
            ['2026-05-11', 'Lainnya', 'Rokok + Minum Kuli',          113000,  null],
            ['2026-05-11', 'Makan',   'Makan',                       42000,   null],
            ['2026-05-11', 'Makan',   'Makan',                       25000,   null],
            ['2026-05-11', 'Belanja', 'Jajan Alfamart',              50000,   null],
            ['2026-05-12', 'Lainnya', 'Parkir',                      163000,  null],
            ['2026-05-12', 'Belanja', 'Indomaret',                   46500,   null],
            ['2026-05-13', 'Makan',   'Minum Kuli',                  36100,   null],
            ['2026-05-13', 'Makan',   'Makan',                       40000,   null],
            ['2026-05-14', 'Belanja', 'Indomaret',                   18200,   null],
            ['2026-05-14', 'Makan',   'Makan',                       44500,   null],
            ['2026-05-14', 'Afuk',    'Makan Afuk',                  50000,   null],
            ['2026-05-15', 'Belanja', 'Indomaret',                   32500,   null],
            ['2026-05-16', 'Makan',   'Kopi Pak Zainal',             44000,   null],
            ['2026-05-16', 'Afuk',    'Uang Afuk',                   100000,  null],
            ['2026-05-16', 'Lainnya', 'Buat Kartu Parkir',           100000,  null],
            ['2026-05-17', 'Belanja', 'Telor + Gula + Mie',          105300,  null],
            ['2026-05-18', 'Lainnya', 'Lalamove',                    82000,   null],
            ['2026-05-19', 'Makan',   'Sei',                         103000,  null],
            ['2026-05-19', 'Makan',   'Makan Diluar',                40000,   null],
            ['2026-05-20', 'Afuk',    'Uang Afuk',                   100000,  null],
            ['2026-05-21', 'Makan',   'Makan Shopeefood',            60000,   null],
            ['2026-05-21', 'Lainnya', 'Sewa Proyektor',              120000,  null],
            ['2026-05-21', 'Lainnya', 'Tol',                         100000,  null],
            ['2026-05-22', 'Go MBG',  'Server Web',                  578000,  null],
            ['2026-05-22', 'Go MBG',  'Server Database',             578000,  null],
            ['2026-05-22', 'Belanja', 'Indomaret',                   32500,   null],
            ['2026-05-23', 'Makan',   'Makan Padang',                92000,   null],
            ['2026-05-23', 'Belanja', 'Indomaret',                   60000,   null],
            ['2026-05-23', 'Afuk',    'Uang Afuk',                   50000,   null],
            ['2026-05-23', 'Go MBG',  'Claude Ridwan',               350000,  null],
            ['2026-05-24', 'Afuk',    'Uang Afuk',                   50000,   null],
            ['2026-05-24', 'Belanja', 'Beras + Telor + Kopi',        136400,  null],
            ['2026-05-24', 'Go MBG',  'Server MBG Store',            68000,   null],
            ['2026-05-24', 'Lainnya', 'Biaya Design Logo Verif PS',  250000,  null],
        ];

        foreach ($data as [$date, $category, $description, $amount, $notes]) {
            Expense::create([
                'expense_date'   => $date,
                'category'       => $category,
                'description'    => $description,
                'amount'         => $amount,
                'notes'          => $notes,
                'recorded_by_id' => null,
            ]);
        }
    }
}
