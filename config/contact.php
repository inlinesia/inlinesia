<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Kartu Kontak Landing Page
    |--------------------------------------------------------------------------
    |
    | Nilai di sini ditampilkan pada kartu kontak di halaman utama. Kosongkan
    | satu field untuk menyembunyikan baris tersebut dari kartu.
    |
    */

    'email' => env('CONTACT_EMAIL', 'inlinesia@gmail.com'),

    'whatsapp' => env('CONTACT_WHATSAPP', ''),

    'phone' => env('CONTACT_PHONE', ''),

    'address' => env('CONTACT_ADDRESS', ''),

    'hours' => env('CONTACT_HOURS', 'Senin–Sabtu, 09.00–18.00 WIB'),

    'instagram' => env('CONTACT_INSTAGRAM', ''),

    'maps_url' => env('CONTACT_MAPS_URL', ''),

    'default_subject' => env('CONTACT_SUBJECT', 'Informasi Inlinesia'),
];
