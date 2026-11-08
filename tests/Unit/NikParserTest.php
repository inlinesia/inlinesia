<?php

namespace Tests\Unit;

use App\Services\NikParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NikParserTest extends TestCase
{
    #[Test]
    public function it_parses_male_nik_with_region_gender_and_birth_date(): void
    {
        // 3504 = Kabupaten Tulungagung, 12 = kecamatan, 120399 = lahir 12-03-1999.
        $result = (new NikParser)->parse('3504121203990001');

        $this->assertTrue($result['valid']);
        $this->assertSame('35', $result['province_code']);
        $this->assertSame('Jawa Timur', $result['province_name']);
        $this->assertSame('3504', $result['regency_code']);
        $this->assertStringContainsString('Tulungagung', (string) $result['regency_name']);
        $this->assertSame('male', $result['gender']);
        $this->assertSame('1999-03-12', $result['birth_date']);
        $this->assertSame(1999, $result['birth_year']);
        $this->assertSame('0001', $result['serial_number']);
        $this->assertNull($result['error']);
    }

    #[Test]
    public function it_detects_female_nik_from_day_above_forty(): void
    {
        // Tanggal 52 berarti 12 dan menandakan perempuan.
        $result = (new NikParser)->parse('3504125203990001');

        $this->assertTrue($result['valid']);
        $this->assertSame('female', $result['gender']);
        $this->assertSame('1999-03-12', $result['birth_date']);
    }

    #[Test]
    public function it_reads_two_digit_year_as_1900s_for_values_above_forty_nine(): void
    {
        $result = (new NikParser)->parse('3504121203750001');

        $this->assertTrue($result['valid']);
        $this->assertSame(1975, $result['birth_year']);
        $this->assertSame('1975-03-12', $result['birth_date']);
    }

    #[Test]
    public function it_rejects_nik_that_is_not_sixteen_digits(): void
    {
        $result = (new NikParser)->parse('350412120399000');

        $this->assertFalse($result['valid']);
        $this->assertSame('NIK harus terdiri dari 16 digit angka.', $result['error']);
    }

    #[Test]
    public function it_rejects_nik_with_unknown_region_code(): void
    {
        $result = (new NikParser)->parse('9904121203990001');

        $this->assertFalse($result['valid']);
        $this->assertSame('Kode provinsi pada NIK tidak dikenali.', $result['error']);
    }

    #[Test]
    public function it_rejects_nik_with_invalid_birth_month(): void
    {
        // Bulan 13 tidak ada.
        $result = (new NikParser)->parse('3504121313990001');

        $this->assertFalse($result['valid']);
        $this->assertSame('Bulan lahir pada NIK tidak valid.', $result['error']);
    }

    #[Test]
    public function it_builds_suggested_address_from_region_names(): void
    {
        $parser = new NikParser;
        $parsed = $parser->parse('3504121203990001');

        $address = $parser->suggestAddress($parsed);

        $this->assertNotNull($address);
        $this->assertStringContainsString('Tulungagung', $address);
        $this->assertStringContainsString('Jawa Timur', $address);
    }
}
