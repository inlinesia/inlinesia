<?php

return [
    'name' => 'Kejurprov XI Piala Bupati Tulungagung',
    'start_date' => '2026-10-02',
    'end_date' => '2026-10-04',
    'venue' => 'GOR Padepokan PSHT Cabang Tulungagung',
    'venue_address' => 'Jl. Papandayan No. 22, Desa Kauman, Kecamatan Kauman, Kabupaten Tulungagung, Jawa Timur',
    'scale' => 'Regional Jawa Timur',
    'regulations' => 'Inline Freestyle Rulebook 2020 — https://www.worldskate.org/inlinefreestyle/about/regulations.html',
    'officials' => 'Head Judge, Scoring Judge, Tabulating Judge, MC, DJ, dan volunteer',
    'venue_conditions' => 'Lantai basement dan interlock.',
    'awards' => 'Medali juara 1–3; Juara Umum; MVP 1 putra dan 1 putri; sertifikat juara 1–3.',
    'categories' => implode("\n", [
        'Beginner',
        'Non-Beginner',
        'Putra dan putri dipisah pada nomor lomba sesuai ketentuan teknis',
    ]),
    'races' => implode("\n", [
        'Classic Slalom',
        'Battle Slide',
        'Battle Slalom',
        'Speed Slalom',
        'Fish Slalom',
        'Skate Cross',
    ]),
    'registration_deadline' => '2026-09-13',
    'registration_info' => 'Form pendaftaran dan musik Classic Slalom dikirim melalui email atau tautan yang diberikan panitia. Musik Classic Slalom berformat MP3 dengan bitrate minimal 128 Kbps. Peserta wajib mengikuti technical meeting.',
    'fees' => implode("\n", [
        'Non-Beginner: pilih 2 dari Classic Slalom, Speed Slalom, Battle Slide, Battle Slalom — Rp350.000.',
        'Non-Beginner: penambahan 1 mata lomba — Rp150.000.',
        'Non-Beginner: Skate Cross — Rp175.000.',
        'Beginner: pilih 2 dari Classic Slalom, Fish Slalom, Skate Cross — Rp350.000.',
        'Beginner: penambahan 1 mata lomba — Rp150.000.',
        'Detail rekening pembayaran diisi oleh penyelenggara sebelum event diterbitkan.',
    ]),
    'force_majeure' => 'Apabila terjadi keadaan di luar kendali panitia yang menghambat pelaksanaan acara (force majeure), biaya pendaftaran tidak dapat dikembalikan.',
    'schedule' => implode("\n", [
        '01 Oktober 2026 — Tes venue dan technical meeting',
        '02 Oktober 2026 — Speed Slalom, kualifikasi Classic Slalom',
        '03 Oktober 2026 — Final Classic Slalom, Battle Slide, Battle Slalom',
        '04 Oktober 2026 — Fish Slalom, Classic Slalom Beginner, Skate Cross',
    ]),
];
