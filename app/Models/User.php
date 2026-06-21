<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;

#[Fillable(['name', 'email', 'password', 'status', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function sendPasswordResetNotification($token): void
    {
        $url  = config('app.url') . '/reset-password?token=' . $token . '&email=' . urlencode($this->email);
        $body = "Halo {$this->name},\n\nKlik link berikut untuk reset password:\n\n{$url}\n\nLink berlaku 60 menit. Abaikan email ini jika kamu tidak meminta reset password.";
        try {
            Mail::raw($body, fn($m) => $m->to($this->email)->subject('Reset Password MBG System'));
        } catch (\Throwable) {}
    }
}
