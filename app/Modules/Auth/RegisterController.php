<?php

namespace App\Modules\Auth;

use App\Models\User;
use App\Modules\Telegram\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

class RegisterController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name'                  => 'required|string|max:100',
            'email'                 => 'required|email|unique:users,email',
            'password'              => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'status'   => 'pending',
        ]);

        $this->notifyAdmin($user);

        return response()->json(['message' => 'Registrasi berhasil. Menunggu persetujuan admin.'], 201);
    }

    public function getUsers()
    {
        return response()->json(User::orderByRaw("FIELD(status, 'pending', 'active', 'rejected')")
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'email', 'status', 'created_at']));
    }

    public function approve(int $id)
    {
        $user = User::findOrFail($id);
        $user->update(['status' => 'active']);
        $this->notifyUser($user, true);
        return response()->json(['message' => "User {$user->name} disetujui."]);
    }

    public function reject(int $id)
    {
        $user = User::findOrFail($id);
        $user->update(['status' => 'rejected']);
        $this->notifyUser($user, false);
        return response()->json(['message' => "User {$user->name} ditolak."]);
    }

    public function pendingCount()
    {
        return response()->json(['count' => User::where('status', 'pending')->count()]);
    }

    private function notifyAdmin(User $user): void
    {
        $chatId = config('services.telegram.admin_chat_id');
        if (!$chatId) return;

        $token  = config('services.telegram.token');
        $apiUrl = "https://api.telegram.org/bot{$token}";
        $text   = "🔔 <b>Request Register Baru</b>\n\n"
                . "Nama  : <b>{$user->name}</b>\n"
                . "Email : {$user->email}\n\n"
                . "Setujui akun ini?";

        Http::post("{$apiUrl}/sendMessage", [
            'chat_id'      => $chatId,
            'text'         => $text,
            'parse_mode'   => 'HTML',
            'reply_markup' => json_encode([
                'inline_keyboard' => [[
                    ['text' => '✅ Approve', 'callback_data' => "reg_approve_{$user->id}"],
                    ['text' => '❌ Reject',  'callback_data' => "reg_reject_{$user->id}"],
                ]],
            ]),
        ]);
    }

    private function notifyUser(User $user, bool $approved): void
    {
        // Opsional: kirim email notifikasi ke user (jika mail dikonfigurasi)
        // Mail::to($user->email)->send(new RegistrationDecision($approved));
    }
}
