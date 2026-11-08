<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'phone', 'organization', 'address'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->is_primary_admin) {
                $user->email = config('primary-admin.email');
                $user->role = 'admin';
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_primary_admin' => 'boolean',
        ];
    }

    public function roleLabel(): string
    {
        return config("user-roles.{$this->role}", 'Pengguna');
    }

    /**
     * Admin Inlinesia, termasuk akun utama yang email dan perannya dikunci.
     *
     * Admin berlaku untuk seluruh data, berbeda dengan pengelola yang hanya
     * berhak atas event atau klub miliknya sendiri.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin' || (bool) $this->is_primary_admin;
    }
}
