<?php

namespace App\Modules\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class PasswordResetController extends Controller
{
    public function sendLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        Password::sendResetLink($request->only('email'));

        // Selalu return sukses — jangan bocorkan apakah email terdaftar atau tidak
        return response()->json(['message' => 'Jika email terdaftar, link reset password akan dikirim ke inbox kamu.']);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => ['required', 'min:8', 'confirmed', 'regex:/[A-Z]/', 'regex:/[0-9]/'],
        ], [
            'password.regex' => 'Password harus mengandung minimal 1 huruf kapital dan 1 angka.',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Password berhasil diubah. Silakan login.'])
            : response()->json(['message' => 'Link tidak valid atau sudah kadaluarsa.'], 422);
    }
}
