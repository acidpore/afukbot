<?php

namespace App\Modules\Auth;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($credentials, true)) {
            return response()->json(['message' => 'Email atau password salah.'], 401);
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

        $request->session()->regenerate();

        return response()->json([
            'user' => [
                'id'    => Auth::user()->id,
                'name'  => Auth::user()->name,
                'email' => Auth::user()->email,
                'role'  => Auth::user()->role,
            ],
        ]);
    }

    public function logout(Request $request)
    {
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
