<?php

namespace App\Modules\Expense;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ReceiptScanController extends Controller
{
    private const GEMINI_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';

    private const PROMPT = <<<'PROMPT'
Kamu adalah parser struk belanja. Dari gambar struk ini, ekstrak semua item beserta harganya.

Kembalikan JSON dengan format persis seperti ini (tanpa markdown, tanpa penjelasan):
{
  "items": [
    {"description": "nama item", "amount": 15000},
    {"description": "nama item lain", "amount": 30000}
  ],
  "total": 45000
}

Aturan:
- amount selalu integer (rupiah), tanpa titik/koma
- Kalau ada qty x harga, kalikan dulu (misal: 2 x 15000 = 30000)
- Abaikan pajak, diskon, nomor struk, nama kasir, dll
- Kalau tidak bisa baca struk, kembalikan: {"items": [], "total": 0}
PROMPT;

    public function scan(Request $request)
    {
        $request->validate([
            'image' => 'required|file|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $apiKey = config('services.gemini.key');
        if (!$apiKey) {
            return $this->sendError('GEMINI_API_KEY belum dikonfigurasi', [], 500);
        }

        $file     = $request->file('image');
        $base64   = base64_encode(file_get_contents($file->getRealPath()));
        $mimeType = $file->getMimeType();

        $response = Http::timeout(30)->post(self::GEMINI_URL . '?key=' . $apiKey, [
            'contents' => [[
                'parts' => [
                    ['text' => self::PROMPT],
                    ['inline_data' => ['mime_type' => $mimeType, 'data' => $base64]],
                ],
            ]],
            'generationConfig' => ['temperature' => 0],
        ]);

        if (!$response->successful()) {
            return $this->sendError('Gagal menghubungi Gemini API', [], 502);
        }

        $text = $response->json('candidates.0.content.parts.0.text', '');
        $text = preg_replace('/```json|```/', '', $text);

        $parsed = json_decode(trim($text), true);
        if (!isset($parsed['items'])) {
            return $this->sendError('Gagal memparse struk. Coba foto lebih jelas.', [], 422);
        }

        return $this->sendResponse($parsed, 'Struk berhasil dipindai');
    }
}
