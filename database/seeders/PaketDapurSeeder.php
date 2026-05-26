<?php

namespace Database\Seeders;

use App\Models\Sale;
use Illuminate\Database\Seeder;

class PaketDapurSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['item_name' => 'Piring Stainless T6',                 'qty' => 2000, 'unit_price' => 38000,    'description' => 'Body 4,5 Tutup 2,5'],
            ['item_name' => 'Meja Stainless 1500x660x850 (Solid)', 'qty' => 12,   'unit_price' => 3100000,  'description' => 'MBG Rekomendasi'],
            ['item_name' => 'Meja Stainless 1500x660x850 (Non Solid)', 'qty' => 6, 'unit_price' => 1800000, 'description' => 'MMT 3 Susun'],
            ['item_name' => 'Rak Susun 4 1750x1500x50',            'qty' => 8,    'unit_price' => 4000000,  'description' => null],
            ['item_name' => 'Steamer 12 Tray',                     'qty' => 2,    'unit_price' => 10000000, 'description' => 'Brand Guarantee BWE'],
            ['item_name' => 'Single Sink 700x700x850',             'qty' => 4,    'unit_price' => 3200000,  'description' => null],
            ['item_name' => 'Double Sink 1200x700x850',            'qty' => 2,    'unit_price' => 4450000,  'description' => null],
            ['item_name' => 'Timbangan Duduk 150Kg',               'qty' => 1,    'unit_price' => 700000,   'description' => 'All Brand Guarantee'],
            ['item_name' => 'Kompor Cor',                          'qty' => 4,    'unit_price' => 260000,   'description' => '31A'],
            ['item_name' => 'Trolly 2 Susun',                      'qty' => 2,    'unit_price' => 1800000,  'description' => null],
            ['item_name' => 'Loading Trolly',                      'qty' => 2,    'unit_price' => 750000,   'description' => '300Kg'],
            ['item_name' => 'Exhaust Fan',                         'qty' => 1,    'unit_price' => 7000000,  'description' => '1 Paket'],
            ['item_name' => 'Water Heater Ariston',                'qty' => 1,    'unit_price' => 2500000,  'description' => 'Teknologi AG+'],
            ['item_name' => 'RSA Chest Freezer',                   'qty' => 2,    'unit_price' => 6000000,  'description' => 'CF 600H 2 Sekat'],
            ['item_name' => 'Loyang Stainless',                    'qty' => 12,   'unit_price' => 140000,   'description' => '60x40x4,8'],
            ['item_name' => 'Nampan 40x30x15',                     'qty' => 10,   'unit_price' => 250000,   'description' => 'Penyajian'],
            ['item_name' => 'Wajan Stainless 80Cm',                'qty' => 4,    'unit_price' => 680000,   'description' => 'Stainless'],
            ['item_name' => 'Panci Merk Jawa',                     'qty' => 4,    'unit_price' => 380000,   'description' => '40Cm'],
            ['item_name' => 'Insect Killer',                       'qty' => 3,    'unit_price' => 200000,   'description' => 'Efektif 100M'],
            ['item_name' => 'Baskom 50Cm',                         'qty' => 8,    'unit_price' => 50000,    'description' => 'AH'],
            ['item_name' => 'Baskom Lubang 34Cm',                  'qty' => 6,    'unit_price' => 35000,    'description' => 'Lubang Kecil'],
            ['item_name' => 'Spatula Jumbo 12Cm',                  'qty' => 12,   'unit_price' => 26000,    'description' => 'Gagang Kayu QQ120'],
            ['item_name' => 'Centong Gagang Jumbo',                'qty' => 12,   'unit_price' => 26000,    'description' => 'Gagang Kayu QQ118'],
            ['item_name' => 'Gunting Dapur Serbaguna',             'qty' => 2,    'unit_price' => 59000,    'description' => 'MM716'],
            ['item_name' => 'Parutan Set Mangkuk Putih',           'qty' => 2,    'unit_price' => 32000,    'description' => 'MS655'],
            ['item_name' => 'Asahan Stainless',                    'qty' => 2,    'unit_price' => 12500,    'description' => 'QQ198'],
            ['item_name' => 'Pisau Gagang Merah 6Inci',            'qty' => 4,    'unit_price' => 10000,    'description' => 'MS 3109 5'],
            ['item_name' => 'Pisau Gagang Putih 8Inci',            'qty' => 6,    'unit_price' => 20000,    'description' => 'MS606'],
            ['item_name' => 'Pisau Daging',                        'qty' => 4,    'unit_price' => 30000,    'description' => 'MS739'],
            ['item_name' => 'Timbangan Dapur Stainless',           'qty' => 2,    'unit_price' => 180000,   'description' => '5Kg'],
            ['item_name' => 'Mangkuk Porsi Nasi 1Lusin',           'qty' => 1,    'unit_price' => 40000,    'description' => 'D11'],
            ['item_name' => 'Kotak P3K',                           'qty' => 1,    'unit_price' => 150000,   'description' => null],
            ['item_name' => 'Apron Desain',                        'qty' => 30,   'unit_price' => 20000,    'description' => null],
            ['item_name' => 'Cetakan Telur 4Sekat',                'qty' => 4,    'unit_price' => 100000,   'description' => null],
            ['item_name' => 'Baskom 65Cm',                         'qty' => 10,   'unit_price' => 85000,    'description' => 'Tinggi 15Cm'],
            ['item_name' => 'Wajan Stainless 55Cm',                'qty' => 2,    'unit_price' => 220000,   'description' => 'SUS304'],
            ['item_name' => 'Nampan 60x40x10',                     'qty' => 10,   'unit_price' => 330000,   'description' => '304 Penyajian'],
            ['item_name' => 'Nampan 450x35x15',                    'qty' => 10,   'unit_price' => 325000,   'description' => 'Penyajian'],
            ['item_name' => 'Kompor High Pressure',                'qty' => 4,    'unit_price' => 3000000,  'description' => 'Tinggi 85Cm'],
            ['item_name' => 'Blender Chopper Hakasima',            'qty' => 2,    'unit_price' => 1200000,  'description' => '3Ltr'],
            ['item_name' => 'Maspion Rice Cooker',                 'qty' => 1,    'unit_price' => 3000000,  'description' => 'GRC100'],
            ['item_name' => 'Telenan 36x52x1,5',                   'qty' => 2,    'unit_price' => 300000,   'description' => 'Putih'],
            ['item_name' => 'Telenan 40x30x1',                     'qty' => 2,    'unit_price' => 150000,   'description' => 'Putih'],
            ['item_name' => 'Grease Trap',                         'qty' => 1,    'unit_price' => 1400000,  'description' => '750x450x325'],
            ['item_name' => 'Rak Lubang 4 Susun',                  'qty' => 4,    'unit_price' => 3000000,  'description' => '150x60x150'],
            ['item_name' => 'Mesin Wortel/Sayur',                  'qty' => 1,    'unit_price' => 5000000,  'description' => 'Machine'],
            ['item_name' => 'Mesin Bumbu',                         'qty' => 1,    'unit_price' => 5000000,  'description' => 'Machine'],
            ['item_name' => 'Mesin Pengupas Bawang',               'qty' => 1,    'unit_price' => 5000000,  'description' => 'Machine'],
            ['item_name' => 'APAR',                                'qty' => 2,    'unit_price' => 750000,   'description' => '5 Kg'],
            ['item_name' => 'Lab Microfiber',                      'qty' => 6,    'unit_price' => 100000,   'description' => 'Grade 6 PCS'],
            ['item_name' => 'Trolly Gastronom',                    'qty' => 1,    'unit_price' => 3000000,  'description' => '15 Susun'],
            ['item_name' => 'Show Case',                           'qty' => 1,    'unit_price' => 9000000,  'description' => '2 Pintu'],
            ['item_name' => 'Dandang',                             'qty' => 3,    'unit_price' => 450000,   'description' => '70 L'],
            ['item_name' => 'Kipas Angin',                         'qty' => 6,    'unit_price' => 500000,   'description' => '18 Inch Wallfan'],
            ['item_name' => 'Pengering Food Tray',                 'qty' => 1,    'unit_price' => 10000000, 'description' => '210Tray'],
        ];

        $grandTotal = array_sum(array_map(fn($i) => $i['qty'] * $i['unit_price'], $items));

        $today  = now()->format('dmy');
        $prefix = "INV-{$today}";
        $last   = Sale::where('invoice_number', 'like', "{$prefix}/%")->orderByDesc('id')->first();
        $seq    = $last
            ? ((int) substr($last->invoice_number, strrpos($last->invoice_number, '/') + 1)) + 1
            : 1;
        $invoiceNumber = $prefix . '/' . str_pad($seq, 4, '0', STR_PAD_LEFT);

        $sale = Sale::create([
            'invoice_number'    => $invoiceNumber,
            'recipient_name'    => 'Paket Dapur',
            'recipient_address' => null,
            'invoice_date'      => now()->toDateString(),
            'notes'             => null,
            'grand_total'       => $grandTotal,
            'paid_amount'       => 0,
            'status'            => 'belum_dikirim',
            'shipped_at'        => null,
        ]);

        foreach ($items as $item) {
            $sale->items()->create([
                'item_name'          => $item['item_name'],
                'description'        => $item['description'],
                'qty'                => $item['qty'],
                'unit_price'         => $item['unit_price'],
                'total_price'        => $item['qty'] * $item['unit_price'],
                'inventory_item_ids' => null,
            ]);
        }
    }
}
