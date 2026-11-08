<?php

/*
|--------------------------------------------------------------------------
| Pilihan Cepat Detail Event
|--------------------------------------------------------------------------
|
| Daftar teks referensi untuk setiap kolom detail event pada halaman
| "Kelola Kejuaraan". Nilai di sini hanya membantu pengisian cepat dan
| tidak membatasi apa pun — Admin tetap bebas mengetik bebas pada kolom
| manual di bawahnya.
|
*/

return [

    'scale' => [
        'custom' => 'Tambah skala event lain',
        'options' => [
            'Sekolah',
            'Kecamatan',
            'Kota',
            'Kabupaten',
            'Provinsi',
            'Regional',
            'Nasional',
        ],
    ],

    'regulations' => [
        'custom' => 'Tambah peraturan event lain',
        'options' => [
            'Mengikuti Inline Freestyle Rulebook World Skate',
            'Wajib memakai helm safety, pelindung lutut, dan pelindung siku',
            'Lintasan minimal 100 meter dengan permukaan datar dan kering',
            'Pengundian nomor dilakukan pada technical meeting',
            'Wajib memakai gloves untuk mata lomba battle',
        ],
    ],

    'officials' => [
        'custom' => 'Tambah official lain',
        'options' => [
            'Head Judge (ketua jovial)',
            'Scoring Judge (penilai waktu)',
            'Tabulating Judge (pencatat hasil)',
            'Referee teknis',
            'MC (pembawa acara)',
            'DJ (pengisi musik)',
            'Koordinator peserta dan liaison',
        ],
    ],

    'venue_conditions' => [
        'custom' => 'Tambah kondisi venue lain',
        'options' => [
            'Lantai basement dan interlock',
            'Permukaan beton halus, tersedia semua',
            'Lantai vinyl indoor dengan pendingin udara',
            'Ruang luas dengan area pong',
            'Toilet dan ruang ganti tersedia',
            'Area parkir dan tribun penonton tersedia',
        ],
    ],

    'categories' => [
        'custom' => 'Tambah kategori peserta lain',
        'options' => [
            'Pr Beginner',
            'Beginner',
            'Non-Beginner',
            'Regu (3 orang)',
            'Putra',
            'Putri',
        ],
    ],

    'races' => [
        'custom' => 'Tambah mata lomba lain',
        'options' => [
            'Classic Slalom',
            'Speed Slalom',
            'Battle Slalom',
            'Battle Slide',
            'Fish Slalom',
            'Skate Cross',
            'Showcase / Demo',
        ],
    ],

    'registration_info' => [
        'custom' => 'Tambah informasi pendaftaran lain',
        'options' => [
            'Pendaftaran melalui portal Inlinesia pada halaman ini',
            'Wajib mengikuti technical meeting',
            'Musik Classic Slalom: format MP3, bitrate minimal 128 Kbps',
            'Datang 30 menit sebelum jadwal nomor',
        ],
    ],

    'fees' => [
        'custom' => 'Tambah biaya dan ketentuan lain',
        'options' => [
            'Pendaftaran awal Rp100.000 per mata lomba',
            'Pendaftaran tambahan Rp50.000 per mata lomba',
            'Early bird potongan 25% hingga 2 minggu sebelum event',
            'Biaya tidak dapat dikembalikan bila peserta membatalkan',
            'Rekening pembayaran diisi penyelenggara sebelum event diterbitkan',
        ],
    ],

    'awards' => [
        'custom' => 'Tambah hadiah lain',
        'options' => [
            'Medali juara 1, 2, dan 3',
            'Piala juara 1',
            'Juara umum',
            'MVP putra dan putri',
            'Sertifikat untuk juara 1 sampai 3',
        ],
    ],

    'force_majeure' => [
        'custom' => 'Tambah ketentuan force majeure lain',
        'options' => [
            'Biaya pendaftaran tidak dapat dikembalikan bila force majeure terjadi',
            'Panitia berhak memindahkan jadwal ke hari lain',
            'Panitia berhak membatalkan nomor lomba bila peserta kurang',
            'Keputusan panitia bersifat mutlak dan diumumkan ke seluruh peserta',
        ],
    ],

    'schedule' => [
        'custom' => 'Tambah agenda jadwal lain',
        'options' => [
            'Hari 1 — Technical meeting dan verifikasi data peserta',
            'Hari 2 — Sesi pagi',
            'Hari 2 — Sesi siang',
            'Hari 3 — Final dan penutupan',        ],
    ],

];
