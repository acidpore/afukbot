<?php

namespace App\Modules\Expense;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Collection;

class ExpenseService
{
    public function getAll(): Collection
    {
        return Expense::orderByDesc('expense_date')->orderByDesc('id')->get();
    }

    public function getById(int $id): Expense
    {
        return Expense::findOrFail($id);
    }

    public function create(array $data): Expense
    {
        return Expense::create([
            'expense_date'   => $data['expense_date'],
            'category'       => $data['category'],
            'description'    => $data['description'],
            'amount'         => $data['amount'],
            'paid_by'        => $data['paid_by'] ?? null,
            'notes'          => $data['notes'] ?? null,
            'recorded_by_id' => auth()->id(),
        ]);
    }

    public function update(int $id, array $data): Expense
    {
        $expense = Expense::findOrFail($id);
        $expense->update([
            'expense_date' => $data['expense_date'],
            'category'     => $data['category'],
            'description'  => $data['description'],
            'amount'       => $data['amount'],
            'paid_by'      => $data['paid_by'] ?? null,
            'notes'        => $data['notes'] ?? null,
        ]);
        return $expense->fresh();
    }

    public function delete(int $id): void
    {
        Expense::findOrFail($id)->delete();
    }

    public function getSummaryByCategory(string $month): array
    {
        return Expense::selectRaw('category, SUM(amount) as total')
            ->whereRaw("DATE_FORMAT(expense_date, '%Y-%m') = ?", [$month])
            ->groupBy('category')
            ->orderByDesc('total')
            ->get()
            ->toArray();
    }

    public function importCsv(\Illuminate\Http\UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        $header = array_map(fn($h) => strtolower(trim($h)), fgetcsv($handle));

        $required = ['tanggal', 'kategori', 'deskripsi', 'jumlah'];
        foreach ($required as $col) {
            if (!in_array($col, $header)) {
                fclose($handle);
                throw new \Exception("Kolom '{$col}' tidak ditemukan. Header wajib: tanggal, kategori, deskripsi, jumlah");
            }
        }

        $imported = 0;
        $skipped  = [];
        $row      = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $row++;
            if (count($data) < count($required)) {
                $skipped[] = "Baris {$row}: kolom tidak lengkap";
                continue;
            }

            $mapped = array_combine($header, array_pad($data, count($header), ''));
            $amount = (int) preg_replace('/[^0-9]/', '', $mapped['jumlah'] ?? '');

            if (empty(trim($mapped['tanggal'])) || empty(trim($mapped['kategori'])) || empty(trim($mapped['deskripsi'])) || $amount < 1) {
                $skipped[] = "Baris {$row}: data tidak valid (kosong atau jumlah 0)";
                continue;
            }

            try {
                $date = \Carbon\Carbon::parse(trim($mapped['tanggal']))->format('Y-m-d');
            } catch (\Exception) {
                $skipped[] = "Baris {$row}: format tanggal tidak dikenali ({$mapped['tanggal']})";
                continue;
            }

            Expense::create([
                'expense_date'   => $date,
                'category'       => trim($mapped['kategori']),
                'description'    => trim($mapped['deskripsi']),
                'amount'         => $amount,
                'paid_by'        => trim($mapped['dibayar_oleh'] ?? '') ?: null,
                'notes'          => trim($mapped['catatan'] ?? '') ?: null,
                'recorded_by_id' => auth()->id(),
            ]);

            $imported++;
        }

        fclose($handle);
        return ['imported' => $imported, 'skipped' => $skipped];
    }
}
