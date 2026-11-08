@php
    $isParticipant = $isParticipant ?? false;
    $editingThisRegistration = (string) old('_registration_id') === (string) $registration->id;
    $value = fn (string $field, mixed $default) => $editingThisRegistration ? old($field, $default) : $default;
    $races = $editingThisRegistration
        ? old('races', $registration->races ?? [])
        : ($registration->races ?? []);
    $isForeign = (bool) ($value('passport_number', $registration->passport_number) || $value('nationality', $registration->nationality));
    $identityValue = $isForeign ? $value('passport_number', $registration->passport_number) : $value('nik', $registration->nik);
    $identityLabel = $isForeign ? 'Nomor paspor' : 'NIK';
    $identityHint = $isForeign
        ? 'Nomor paspor sesuai dokumen yang Anda miliki. Tanpa NIK, jenis kelamin dan tanggal lahir diisi sendiri di bawah. Nomor ini disimpan terenkripsi dan hanya dipakai untuk identifikasi pendaftaran.'
        : '16 digit. Wilayah, jenis kelamin, dan tanggal lahir akan terisi otomatis. Nomor ini disimpan terenkripsi dan hanya digunakan untuk identifikasi pendaftaran.';
@endphp

<dialog
    id="ubah-peserta-{{ $registration->id }}"
    class="event-registration-modal"
    aria-labelledby="ubah-peserta-title-{{ $registration->id }}"
    @if ($editingThisRegistration) data-open-on-load @endif
>
    <section class="account-edit-card event-registration-edit-card">
        <header class="event-registration-modal-heading">
            <div>
                <span class="account-eyebrow">{{ $event->name }}</span>
                <h2 id="ubah-peserta-title-{{ $registration->id }}">Ubah data atlet</h2>
                <p>{{ $registration->participant_name }} · kategori: {{ $registration->category ?: 'Menunggu konfirmasi pembayaran' }}</p>
            </div>
            <button class="event-registration-modal-close" type="button" data-registration-modal-close aria-label="Tutup">×</button>
        </header>

        @if ($editingThisRegistration && $errors->any())
            <div class="account-errors" role="alert">
                <strong>Periksa kembali data atlet.</strong>
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        @if (! $isParticipant && $registration->payment_proof_path)
            <p class="event-registration-proof-link"><a class="account-edit-link" href="{{ route('events.registrations.proof', [$event, $registration]) }}" target="_blank" rel="noopener">Lihat bukti pembayaran</a></p>
        @endif

        <form class="account-form event-registration-edit-form" method="POST" action="{{ $isParticipant ? route('events.registration.update-own', [$event, $registration]) : route('events.registrations.update', [$event, $registration]) }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="_registration_id" value="{{ $registration->id }}">

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
            <label data-identity-foreign @if (! $isForeign) hidden @endif>Negara asal
                <input name="nationality" value="{{ $value('nationality', $registration->nationality) }}" maxlength="80" placeholder="Contoh: Singapura" @if ($isForeign) required @endif>
                <small class="field-hint">Tulis nama negara asal yang tercantum pada paspor Anda.</small>
            </label>
            <label>Nama lengkap
                <input name="participant_name" value="{{ $value('participant_name', $registration->participant_name) }}" maxlength="120" required>
            </label>
            <label>Nama panggilan <span class="optional-label">opsional</span>
                <input name="nickname" value="{{ $value('nickname', $registration->nickname) }}" maxlength="80">
            </label>
            <label>Jenis kelamin
                <select name="gender" required>
                    <option value="male" @selected($value('gender', $registration->gender) === 'male')>Laki-laki</option>
                    <option value="female" @selected($value('gender', $registration->gender) === 'female')>Perempuan</option>
                </select>
            </label>
            <label>Tanggal lahir
                <input name="date_of_birth" type="date" value="{{ $value('date_of_birth', $registration->date_of_birth?->format('Y-m-d')) }}" max="{{ today()->format('Y-m-d') }}" required>
            </label>
            <label>Nama orang tua / wali
                <input name="guardian_name" value="{{ $value('guardian_name', $registration->guardian_name) }}" maxlength="120" required>
            </label>
            <label>WhatsApp
                <input name="whatsapp" type="tel" value="{{ $value('whatsapp', $registration->whatsapp) }}" maxlength="40" required>
            </label>
            <label>Klub <span class="optional-label">opsional</span>
                <input name="club_name" value="{{ $value('club_name', $registration->club_name) }}" maxlength="180">
            </label>
            <label class="event-registration-full-width">Alamat lengkap terbaru
                <textarea name="address" rows="3" maxlength="1000" autocomplete="street-address" required>{{ $value('address', $registration->address) }}</textarea>
            </label>

            <!-- Kategori Paket (Wajibikuti) -->
<fieldset class="role-options event-registration-races" data-category-type="paket">
    <legend>Mata Lomba Paket <span class="optional-label">(Wajibikuti)</span></legend>
    @foreach ($event->races as $race)
        <label class="role-option">
            <input type="checkbox" name="races_paket[]" value="{{ $race }}" @checked(in_array($race, old('races_paket', []), true))]
            <span><strong>{{ $race }}</strong></span>
        </label>
    @endforeach
</fieldset>

<!-- Kategori Tambahan (Luar Kategori) -->
@if (! empty($registration->category) && ! empty($registration->category->outside_obligation))
    @if ($registration->category->outside_obligation)
        <fieldset class="role-options event-registration-races" data-category-type="tambahan">
            <legend>Mata Lomba Tambahan <span class="optional-label">(Luar Kategori)</span></legend>
            @foreach ($event->races as $race)
                <label class="role-option">
                    <input type="checkbox" name="races_tambahan[]" value="{{ $race }}" @checked(in_array($race, old('races_tambahan', []), true))]
                    <span><strong>{{ $race }}</strong></span>
                </label>
            @endforeach
        </fieldset>
    @endif
@endif

            @if ($isParticipant)
                <p class="event-registration-category-note">
                    {{ $registration->payment_status === 'paid'
                        ? 'Perubahan ini akan ditinjau pengelola. Jika biaya berubah, kekurangan menjadi tagihan tambahan atau kelebihan dicatat sebagai refund.'
                        : 'Data yang diperbarui akan langsung disimpan. Jika sebelumnya sudah mengirim bukti pembayaran, bukti tersebut perlu dikirim kembali.' }}
                </p>
                <button class="account-submit" type="submit">Kirim perubahan <span aria-hidden="true">→</span></button>
            @else
                <label>Status pendaftaran
                    <select name="status" required>
                        <option value="pending" @selected($value('status', $registration->status) === 'pending')>Menunggu panitia</option>
                        <option value="approved" @selected($value('status', $registration->status) === 'approved')>Diterima</option>
                        <option value="rejected" @selected($value('status', $registration->status) === 'rejected')>Perlu ditinjau</option>
                    </select>
                </label>
                <p class="event-registration-category-note">Status pembayaran dikonfirmasi melalui tombol di samping status pendaftaran setelah peserta mengirim bukti atau konfirmasi melalui WhatsApp.</p>
                <button class="account-submit" type="submit">Simpan data atlet <span aria-hidden="true">→</span></button>
            @endif
        </form>
    </section>
</dialog>
