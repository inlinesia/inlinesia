@extends('layouts.public')

@section('title', 'Daftar Peserta — '.$event->name)
@section('description', 'Daftarkan peserta ke '.$event->name.'.')
@section('body-class', 'auth-page')

@section('content')
<main class="account-page account-page-login">
    <section class="account-intro">
        <span class="account-eyebrow">PENDAFTARAN PESERTA</span>
        <h1>{{ $event->name }}</h1>
        <p>{{ $event->start_date->format('d/m/Y') }}–{{ $event->end_date->format('d/m/Y') }} · {{ $event->venue }}, {{ $event->venue_address }}</p>
        @if (session('status'))<div class="public-flash" role="status">{{ session('status') }}</div>@endif
        <a class="account-back-link" href="{{ route('events.index') }}">← Kembali ke daftar lomba</a>
    </section>
    <section class="account-card">
        <div class="account-card-heading"><span class="account-eyebrow">AKUN {{ auth()->user()->email }}</span><h2>Biodata atlet</h2><p>Kategori baru ditetapkan setelah {{ $event->is_free ? 'pendaftaran dikonfirmasi' : 'pembayaran dikonfirmasi' }} oleh pemilik kejuaraan, berdasarkan tanggal lahir, gender, mata lomba, dan aturan kategori. Setelah dikirim, data hanya dapat diubah pemilik kejuaraan.</p></div>
        @if ($errors->any())<div class="account-errors" role="alert"><strong>Periksa kembali data pendaftaran.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <div class="event-payment-instructions">
            <strong>{{ $event->is_free ? 'Pendaftaran gratis' : 'Biaya & ketentuan pembayaran' }}</strong>
            <p>{{ $event->fees }}</p>
            @if (! $event->is_free && $event->hasPaymentAccount())
                <div class="event-payment-account">
                    <span>Rekening pembayaran</span>
                    @if ($event->payment_bank_name)
                        <span>{{ $event->payment_bank_name }}</span>
                    @endif
                    <strong>{{ $event->payment_account_number }}</strong>
                    @if ($event->payment_account_holder)
                        <span>a.n. {{ $event->payment_account_holder }}</span>
                    @endif
                </div>
            @endif
            <small>{{ $event->is_free ? 'Setelah mendaftar, konfirmasikan pendaftaran melalui WhatsApp pemilik event.' : 'Setelah membayar sesuai ketentuan, unggah bukti pembayaran atau kirim konfirmasi melalui WhatsApp.' }}</small>
        </div>
        @php
            $isForeign = (bool) (old('passport_number') || old('nationality'));
            $hasOutsideObligation = collect($event->category_rules)->contains(fn ($rule) => ! empty($rule['outside_obligation']));
            $identityValue = $isForeign ? old('passport_number') : old('nik');
            $identityLabel = $isForeign ? 'Nomor paspor' : 'NIK';
            $identityHint = $isForeign
                ? 'Nomor paspor sesuai dokumen yang Anda miliki. Tanpa NIK, jenis kelamin dan tanggal lahir diisi sendiri di bawah. Nomor ini disimpan terenkripsi dan hanya dipakai untuk identifikasi pendaftaran.'
                : '16 digit. Wilayah, jenis kelamin, dan tanggal lahir akan terisi otomatis. Nomor ini disimpan terenkripsi dan hanya digunakan untuk identifikasi pendaftaran.';
        @endphp
        @if ($registrations->isNotEmpty())
            <div class="event-user-registrations"><strong>Atlet yang sudah didaftarkan</strong>@foreach ($registrations as $existing)<span>{{ $existing->participant_name }} · {{ $existing->category }} · {{ $existing->status === 'pending' ? 'Menunggu panitia' : $existing->status }}</span>@endforeach @if ($event->isParticipantListReleased())<a class="account-edit-link" href="{{ route('events.registrations.index', $event) }}">Lihat daftar peserta <span aria-hidden="true">→</span></a>@else<a class="account-edit-link" href="{{ route('guest.dashboard') }}#pendaftaran-saya">Lihat pendaftaran saya <span aria-hidden="true">→</span></a>@endif</div>
        @endif
        <form class="account-form athlete-registration-form" method="POST" action="{{ route('events.registration.store', $event) }}" data-athlete-registration data-nik-autofill data-nik-autofill-endpoint="{{ route('nik.lookup') }}" data-event-date="{{ $event->start_date->format('Y-m-d') }}" data-category-rules="{{ json_encode($event->category_rules, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) }}" data-category-statuses="{{ json_encode(collect(array_column($event->category_rules ?? [], 'name'))->mapWithKeys(fn (string $name): array => [
                $name => [
                    'open' => $event->isCategoryRegistrationOpen($name),
                    'override' => $event->categoryRule($name)['registration_override'] ?? 'auto',
                    'quota' => $event->categoryQuota($name),
                    'confirmed' => $event->confirmedCategoryCount($name),
                ],
            ])->all(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) }}">
            @csrf
            <label>Kewarganegaraan
                <select name="is_foreign_national" data-foreign-toggle>
                    <option value="0" @selected(! $isForeign)>Warga negara Indonesia (punya NIK)</option>
                    <option value="1" @selected($isForeign)>Warga negara lain (punya paspor)</option>
                </select>
            </label>
            <label>
                <span data-identity-label>{{ $identityLabel }}</span>
                <input data-identity-input name="{{ $isForeign ? 'passport_number' : 'nik' }}" autocomplete="off" value="{{ $identityValue }}" maxlength="60" required @unless ($isForeign) inputmode="numeric" minlength="16" maxlength="16" pattern="[0-9]{16}" @endunless>
                <small class="field-hint" data-identity-hint>{{ $identityHint }}</small>
            </label>
            <p class="nik-feedback" data-nik-feedback aria-live="polite" hidden></p>
            <label data-identity-foreign @if (! $isForeign) hidden @endif>Negara asal
                <input name="nationality" value="{{ old('nationality') }}" maxlength="80" placeholder="Contoh: Singapura" @if ($isForeign) required @endif>
                <small class="field-hint">Tulis nama negara asal yang tercantum pada paspor Anda.</small>
            </label>
            <label>Nama lengkap <input name="participant_name" autocomplete="name" value="{{ old('participant_name', auth()->user()->role === 'individual' ? auth()->user()->name : '') }}" maxlength="120" required></label>
            <label>Nama panggilan <span class="optional-label">opsional</span><input name="nickname" value="{{ old('nickname') }}" maxlength="80"></label>
            <label>Jenis kelamin
                <select name="gender" required id="registration-gender"><option value="">Pilih jenis kelamin</option><option value="male" @selected(old('gender') === 'male')>Laki-laki</option><option value="female" @selected(old('gender') === 'female')>Perempuan</option></select>
                <small class="field-hint" data-identity-local>Terisi otomatis dari NIK dan dikunci, karena jenis kelamin merupakan bagian dari NIK. Jika ada perbedaan, silakan periksa kembali NIK Anda.</small>
                <small class="field-hint" data-identity-foreign hidden>Warga negara lain mengisi sendiri, karena tidak ada NIK. Pastikan sesuai dengan paspor Anda.</small>
            </label>
            <label>Tanggal lahir <input name="date_of_birth" type="date" value="{{ old('date_of_birth') }}" max="{{ today()->format('Y-m-d') }}" required id="registration-dob">
                <small class="field-hint" data-identity-local>Terisi otomatis dari NIK dan dikunci, karena tanggal lahir merupakan bagian dari NIK. Jika ada perbedaan, silakan periksa kembali NIK Anda.</small>
                <small class="field-hint" data-identity-foreign hidden>Warga negara lain diisi sendiri. Tanggal lahir ini yang menentukan kategori umur.</small>
            </label>
            <label>Alamat lengkap terbaru <textarea name="address" rows="3" maxlength="1000" autocomplete="street-address" required>{{ old('address') }}</textarea><small class="field-hint">Terisi otomatis dari wilayah NIK bila NIK tersedia, dan tetap dapat disunting bila peserta berpindah domisili.</small></label>
            <label>Nama orang tua / wali <input name="guardian_name" value="{{ old('guardian_name', auth()->user()->role === 'individual' ? '' : auth()->user()->name) }}" maxlength="120" required></label>
            <label>WhatsApp yang bisa dihubungi <input name="whatsapp" type="tel" autocomplete="tel" value="{{ old('whatsapp', auth()->user()->phone) }}" maxlength="40" required></label>
            <label>Klub <span class="optional-label">opsional</span><input name="club_name" value="{{ old('club_name', auth()->user()->organization) }}" maxlength="180"></label>
            <p class="category-assignment-preview" data-category-assignment-preview aria-live="polite">Kategori atlet akan ditetapkan setelah {{ $event->is_free ? 'pendaftaran dikonfirmasi' : 'pembayaran dikonfirmasi' }}.</p>
            <fieldset class="role-options" data-category-type="paket"><legend>Mata Lomba <span class="optional-label">(Pilih sesuai kategori)</span></legend>
                <small class="field-hint">Pilih mata lomba yang ingin diikuti. Kategori, mata lomba paket (wajib), dan mata lomba di luar kategori beserta harganya akan muncul otomatis setelah Anda mengisi jenis kelamin dan tanggal lahir.</small>
                @foreach ($event->races as $race)
                    <label class="role-option"><input type="checkbox" name="races[]" value="{{ $race }}" @checked(in_array($race, old('races', []), true))><span><strong>{{ $race }}</strong></span></label>
                @endforeach
            </fieldset>
            @if ($hasOutsideObligation)
                <p class="field-hint" style="margin-top:-8px;">Mata lomba yang termasuk <strong>di luar kategori</strong> (tambahan) dikenakan biaya per mata lomba terpisah dari harga paket. Rinciannya muncul di pratinjau kategori setelah Anda memilih mata lomba.</p>
            @endif
            <button class="account-submit" type="submit">Kirim pendaftaran <span aria-hidden="true">→</span></button>
        </form>
    </section>
</main>
@endsection
