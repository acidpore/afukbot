<?php

namespace App\Modules\Expense;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

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
        if ($expense->expense_transaction_id) {
            throw new \Exception('Pengeluaran ini berasal dari RAB dan hanya bisa diubah melalui tab RAB.');
        }
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
        $expense = Expense::findOrFail($id);
        if ($expense->expense_transaction_id) {
            throw new \Exception('Pengeluaran ini berasal dari RAB dan hanya bisa dihapus melalui tab RAB.');
        }
        if ($expense->receipt_path) {
            Storage::disk('public')->delete($expense->receipt_path);
        }
        $expense->delete();
    }

    public function uploadReceipt(int $id, \Illuminate\Http\UploadedFile $file): Expense
    {
        $expense = Expense::findOrFail($id);
        if ($expense->expense_transaction_id) {
            throw new \Exception('Bukti struk untuk pengeluaran RAB hanya bisa diupload melalui tab RAB.');
        }
        if ($expense->receipt_path) {
            Storage::disk('public')->delete($expense->receipt_path);
        }
        $path = $file->store('receipts', 'public');
        $expense->update(['receipt_path' => $path]);
        return $expense->fresh();
    }

    public function deleteReceipt(int $id): Expense
    {
        $expense = Expense::findOrFail($id);
        if ($expense->expense_transaction_id) {
            throw new \Exception('Bukti struk untuk pengeluaran RAB hanya bisa dihapus melalui tab RAB.');
        }
        if ($expense->receipt_path) {
            Storage::disk('public')->delete($expense->receipt_path);
            $expense->update(['receipt_path' => null]);
        }
        return $expense->fresh();
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
        $handle    = fopen($file->getRealPath(), 'r');
        $firstLine = fgets($handle);
        rewind($handle);

        // Deteksi separator: titik koma (Excel Indonesia) atau koma
        $sep    = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
        $header = array_map(fn($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $h))), fgetcsv($handle, 0, $sep));

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

        while (($data = fgetcsv($handle, 0, $sep)) !== false) {
            $row++;
            if (!$data || count($data) < count($required)) {
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
                $raw  = trim($mapped['tanggal']);
                $date = preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $raw)
                    ? \Carbon\Carbon::createFromFormat('d/m/Y', $raw)->format('Y-m-d')
                    : \Carbon\Carbon::parse($raw)->format('Y-m-d');
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
