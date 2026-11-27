<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicensePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_footer_menampilkan_copyright_damai_dan_tautan_lisensi(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSeeText('© 2026 - Damai digital creative')
            ->assertSee(route('license'), false)
            ->assertSeeText('Lisensi');
    }

    public function test_halaman_lisensi_dapat_dibuka_tanpa_login(): void
    {
        $this->get(route('license'))
            ->assertOk()
            ->assertSeeText('Lisensi & Harga')
            ->assertSeeText('Damai digital creative')
            ->assertSeeText('Lisensi 1x Beli (Source Code):')
            ->assertSeeText('Undang-Undang Hak Cipta No. 28 Tahun 2014')
            ->assertSeeText('Rp 149.000/event')
            ->assertDontSeeText('Harga akan diumumkan')
            ->assertDontSeeText('Rp 49.000.000')
            ->assertDontSeeText('Rp 3.500.000')
            ->assertSee('https://damai.kilatschool.my.id/', false)
            ->assertSeeText('085800006248');
    }

    public function test_halaman_lisensi_menampilkan_perbandingan_paket_event(): void
    {
        $this->get(route('license'))
            ->assertOk()
            ->assertSeeText('Perbandingan paket event')
            ->assertSeeText('Iklan event')
            ->assertSeeText('Kelola penuh')
            ->assertSeeText('Rp 0')
            ->assertSeeText('Sponsor website & event')
            ->assertSeeText('Rp 250.000–1.000.000/bulan')
            ->assertSeeText('Rp 150.000–300.000/event')
            ->assertDontSeeText('harga belum ditetapkan');
    }
}
