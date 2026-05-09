<?php

namespace App\Modules\Telegram;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TelegramController extends Controller
{
    public function __construct(private TelegramService $telegramService) {}

    public function webhook(Request $request)
    {
        $this->telegramService->handle($request->all());
        return response()->json(['ok' => true]);
    }
}
