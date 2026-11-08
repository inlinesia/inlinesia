<?php

namespace App\Http\Controllers;

use App\Services\NikParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Membaca makna satu NIK untuk mengisi biodata pendaftaran secara otomatis.
 *
 * Dipanggil dari halaman pendaftaran saat peserta selesai mengisi NIK, supaya
 * wilayah, gender, dan tanggal lahir langsung terisi. Gender dan tanggal lahir
 * diambil dari NIK dan tidak boleh diubah; wilayah hanya menjadi nilai awal
 * kolom alamat yang tetap boleh disunting.
 */
class NikLookupController extends Controller
{
    public function __invoke(Request $request, NikParser $parser): JsonResponse
    {
        $validated = $request->validate([
            'nik' => ['required', 'string', 'regex:/^[0-9]{16}$/'],
        ]);

        $parsed = $parser->parse($validated['nik']);

        if (! $parsed['valid']) {
            return response()->json([
                'valid' => false,
                'message' => $parsed['error'],
            ], 422);
        }

        return response()->json([
            'valid' => true,
            'gender' => $parsed['gender'],
            'date_of_birth' => $parsed['birth_date'],
            'province' => $parsed['province_name'],
            'regency' => $parsed['regency_name'],
            'district' => $parsed['district_name'],
            'address_suggestion' => $parser->suggestAddress($parsed),
        ]);
    }
}
