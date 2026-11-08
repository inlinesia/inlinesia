<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Atlet yang tercatat di direktori Inlinesia.
 *
 * Berbeda dengan pendaftaran lomba, satu atlet di sini belum tentu punya
 * event. Afiliasi ini dipakai supaya biodata dan NIK cukup diisi sekali,
 * lalu dipakai lagi setiap kali mendaftar lomba lain.
 *
 * NIK adalah data pribadi. Ia hanya boleh dilihat oleh pemilik akunnya
 * sendiri dan oleh pemilik klub tempat atlet itu terdaftar. Semua akun
 * lain hanya melihat nama.
 */
#[Hidden(['nik', 'nik_hash'])]
class Athlete extends Model
{
    protected $fillable = [
        'name',
        'gender',
        'date_of_birth',
        'nik',
        'nik_hash',
        'address',
        'phone',
        'guardian_name',
        'club_id',
        'owner_user_id',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'nik' => 'encrypted',
            'gender' => 'string',
            'club_id' => 'integer',
            'owner_user_id' => 'integer',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * NIK boleh dibuka oleh pemilik akunnya dan oleh pemilik klubnya.
     */
    public function canViewNik(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($this->owner_user_id === $user->id) {
            return true;
        }

        return $this->club !== null && $this->club->isManagedBy($user);
    }

    /**
     * NIK yang disamarkan untuk tampilan yang tidak berhak melihatnya.
     */
    public function maskedNik(): string
    {
        $nik = (string) $this->nik;

        if (strlen($nik) < 4) {
            return str_repeat('•', 8);
        }

        return str_repeat('•', 8).substr($nik, -4);
    }

    /**
     * Pencarian berdasarkan nama atlet.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $query->where('name', 'like', '%'.$term.'%');
    }
}
