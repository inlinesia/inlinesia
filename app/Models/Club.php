<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Klub Inline Skate yang dikenal Inlinesia.
 *
 * Klub bisa muncul lebih dulu lewat formulir publik, sebelum ikut lomba
 * apa pun, supaya orang yang mencari klub punya tempat untuk menghubungi
 * pengelolanya.
 */
class Club extends Model
{
    protected $fillable = [
        'name',
        'city',
        'contact_name',
        'contact_phone',
        'contact_email',
        'description',
        'owner_user_id',
    ];

    protected function casts(): array
    {
        return [
            'owner_user_id' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function athletes(): HasMany
    {
        return $this->hasMany(Athlete::class);
    }

    /**
     * Hanya pemilik klub yang boleh mengubah data klub ini.
     */
    public function isManagedBy(?User $user): bool
    {
        return $user !== null && $this->owner_user_id === $user->id;
    }
}
