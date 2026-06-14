<?php

namespace App\Modules\Auth;

use App\Models\ActivityLog;
use App\Models\User;
use App\Modules\Telegram\TelegramService;
use App\Services\WebPushService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

class RegisterController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email:rfc,dns|unique:users,email',
            'password' => [
                'required', 'string', 'min:8', 'confirmed',
                'regex:/[A-Z]/',      // huruf kapital
                'regex:/[0-9]/',      // angka
            ],
        ], [
            'password.regex' => 'Password harus mengandung minimal 1 huruf kapital dan 1 angka.',
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'status'   => 'pending',
        ]);

        $this->notifyAdmin($user);

        try {
            app(WebPushService::class)->sendToSuperAdmins(
                'User Baru Menunggu Persetujuan',
                "{$user->name} ({$user->email}) baru saja mendaftar.",
                '/dashboard?tab=settings'
            );
        } catch (\Throwable) {}

        return response()->json(['message' => 'Registrasi berhasil. Menunggu persetujuan admin.'], 201);
    }

    public function getUsers()
    {
        return response()->json(User::orderByRaw("FIELD(status, 'pending', 'active', 'rejected')")
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'email', 'status', 'role', 'created_at']));
    }

    public function approve(int $id)
    {
        $user = User::findOrFail($id);
        $user->update(['status' => 'active']);
        $this->notifyUser($user, true);
        ActivityLog::record('user_approved', "Akun {$user->name} ({$user->email}) disetujui");
        return response()->json(['message' => "User {$user->name} disetujui."]);
    }

    public function reject(int $id)
    {
        $user = User::findOrFail($id);
        $user->update(['status' => 'rejected']);
        $this->notifyUser($user, false);
        ActivityLog::record('user_rejected', "Akun {$user->name} ({$user->email}) ditolak");
        return response()->json(['message' => "User {$user->name} ditolak."]);
    }

    public function destroy(int $id)
    {
        $user = User::findOrFail($id);

        if ($user->role === 'super_admin') {
            return response()->json(['message' => 'Super admin tidak bisa dihapus.'], 403);
        }

        ActivityLog::record('user_deleted', "Akun {$user->name} ({$user->email}) dihapus");
        $user->delete();
        return response()->json(['message' => "Akun {$user->name} dihapus."]);
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
