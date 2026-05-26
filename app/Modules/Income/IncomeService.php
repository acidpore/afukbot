<?php

namespace App\Modules\Income;

use App\Models\Income;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

class IncomeService
{
    public function getAll(): Collection
    {
        return Income::orderByDesc('income_date')->orderByDesc('id')->get();
    }

    public function getById(int $id): Income
    {
        return Income::findOrFail($id);
    }

    public function create(array $data): Income
    {
        return Income::create([
            'income_date'    => $data['income_date'],
            'source'         => $data['source'],
            'description'    => $data['description'],
            'amount'         => $data['amount'],
            'notes'          => $data['notes'] ?? null,
            'recorded_by_id' => auth()->id(),
        ]);
    }

    public function update(int $id, array $data): Income
    {
        $income = Income::findOrFail($id);
        $income->update([
            'income_date' => $data['income_date'],
            'source'      => $data['source'],
            'description' => $data['description'],
            'amount'      => $data['amount'],
            'notes'       => $data['notes'] ?? null,
        ]);
        return $income->fresh();
    }

    public function delete(int $id): void
    {
        $income = Income::findOrFail($id);
        if ($income->receipt_path) {
            Storage::disk('public')->delete($income->receipt_path);
        }
        $income->delete();
    }

    public function uploadReceipt(int $id, \Illuminate\Http\UploadedFile $file): Income
    {
        $income = Income::findOrFail($id);
        if ($income->receipt_path) {
            Storage::disk('public')->delete($income->receipt_path);
        }
        $path = $file->store('receipts', 'public');
        $income->update(['receipt_path' => $path]);
        return $income->fresh();
    }

    public function deleteReceipt(int $id): Income
    {
        $income = Income::findOrFail($id);
        if ($income->receipt_path) {
            Storage::disk('public')->delete($income->receipt_path);
            $income->update(['receipt_path' => null]);
        }
        return $income->fresh();
    }

    public function importCsv(\Illuminate\Http\UploadedFile $file): array
    {
        $handle    = fopen($file->getRealPath(), 'r');
        $firstLine = fgets($handle);
        rewind($handle);

        $sep    = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
        $header = array_map(fn($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $h))), fgetcsv($handle, 0, $sep));

        $required = ['tanggal', 'sumber', 'deskripsi', 'jumlah'];
        foreach ($required as $col) {
            if (!in_array($col, $header)) {
                fclose($handle);
                throw new \Exception("Kolom '{$col}' tidak ditemukan. Header wajib: tanggal, sumber, deskripsi, jumlah");
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

            if (empty(trim($mapped['tanggal'])) || empty(trim($mapped['sumber'])) || empty(trim($mapped['deskripsi'])) || $amount < 1) {
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

            Income::create([
                'income_date'    => $date,
                'source'         => trim($mapped['sumber']),
                'description'    => trim($mapped['deskripsi']),
                'amount'         => $amount,
                'notes'          => trim($mapped['catatan'] ?? '') ?: null,
                'recorded_by_id' => auth()->id(),
            ]);

            $imported++;
        }

        fclose($handle);
        return ['imported' => $imported, 'skipped' => $skipped];
    }
}
