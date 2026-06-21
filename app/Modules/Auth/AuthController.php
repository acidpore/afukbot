<?php

namespace App\Modules\Auth;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 15;

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $email = $credentials['email'];
        $ip    = $request->ip();

        $recentFails = DB::table('login_attempts')
            ->where('email', $email)
            ->where('success', false)
            ->where('attempted_at', '>=', now()->subMinutes(self::LOCKOUT_MINUTES))
            ->count();

        if ($recentFails >= self::MAX_ATTEMPTS) {
            DB::table('login_attempts')->insert(['email' => $email, 'ip_address' => $ip, 'success' => false]);
            return response()->json([
                'message' => 'Akun terkunci sementara karena terlalu banyak percobaan login gagal. Coba lagi dalam ' . self::LOCKOUT_MINUTES . ' menit.',
            ], 429);
        }

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            DB::table('login_attempts')->insert(['email' => $email, 'ip_address' => $ip, 'success' => false]);
            $remaining = self::MAX_ATTEMPTS - $recentFails - 1;
            $msg = 'Email atau password salah.';
            if ($remaining <= 2 && $remaining > 0) {
                $msg .= " Sisa {$remaining} percobaan sebelum akun terkunci.";
            }
            return response()->json(['message' => $msg], 401);
        }

        $user = Auth::user();
        if ($user->status === 'pending') {
            Auth::logout();
            return response()->json(['message' => 'Akun kamu sedang menunggu persetujuan admin.'], 403);
        }
        if ($user->status === 'rejected') {
            Auth::logout();
            return response()->json(['message' => 'Akun kamu telah ditolak oleh admin.'], 403);
        }

        DB::table('login_attempts')->insert(['email' => $email, 'ip_address' => $ip, 'success' => true]);
        ActivityLog::record('login', "Login berhasil dari IP {$ip}", $user->id);

        $request->session()->regenerate();

        return response()->json([
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        ActivityLog::record('logout', 'User logout');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['message' => 'Logged out.']);
    }

    public function me()
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = Auth::user();

        $permissions = [];
        if ($user->role === 'admin') {
            $saved = $user->permissions->keyBy('feature');
            foreach (\App\Modules\Auth\AdminPermissionController::FEATURES as $feature) {
                $perm = $saved->get($feature);
                $permissions[$feature] = [
                    'can_view'   => $perm ? $perm->can_view   : true,
                    'can_create' => $perm ? $perm->can_create : false,
                    'can_edit'   => $perm ? $perm->can_edit   : false,
                    'can_delete' => $perm ? $perm->can_delete : false,
                    'can_adjust' => $perm ? $perm->can_adjust : false,
                ];
            }
        }

        return response()->json([
            'user' => [
                'id'          => $user->id,
                'name'        => $user->name,
                'email'       => $user->email,
                'role'        => $user->role,
                'permissions' => $permissions,
            ],
        ]);
    }
}
