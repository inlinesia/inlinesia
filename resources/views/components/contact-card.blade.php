@php
    $contactEmail = config('contact.email');
    $contactWhatsapp = config('contact.whatsapp');
    $contactPhone = config('contact.phone');
    $contactAddress = config('contact.address');
    $contactHours = config('contact.hours');
    $contactInstagram = config('contact.instagram');
    $contactMaps = config('contact.maps_url');
    $contactSubject = config('contact.default_subject');

    $whatsappDigits = $contactWhatsapp ? preg_replace('/\D+/', '', $contactWhatsapp) : null;
    $phoneDigits = $contactPhone ? preg_replace('/\D+/', '', $contactPhone) : null;
@endphp

<section class="public-contact" id="kontak" aria-labelledby="contact-heading">
    <div class="contact-heading">
        <span class="public-section-label">HUBUNGI KAMI</span>
        <h2 id="contact-heading">Kontak <span>Inlinesia.</span></h2>
        <p>Butuh informasi kejuaraan, ingin Jadi pengelola event, atau ingin bekerja sama? Sampaikan lewat salah satu kanal di bawah ini.</p>
    </div>

    <div class="contact-grid">
        <article class="contact-item">
            <span class="contact-icon" aria-hidden="true">✉</span>
            <div>
                <span class="contact-label">EMAIL</span>
                @if ($contactEmail)
                    <a class="contact-value" href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>
                    <a class="contact-action" href="mailto:{{ $contactEmail }}?subject={{ urlencode($contactSubject) }}">Kirim pesan <span aria-hidden="true">→</span></a>
                @else
                    <span class="contact-value is-empty">Belum diisi</span>
                @endif
            </div>
        </article>

        @if ($whatsappDigits)
            <article class="contact-item">
                <span class="contact-icon" aria-hidden="true">◍</span>
                <div>
                    <span class="contact-label">WHATSAPP</span>
                    <a class="contact-value" href="https://wa.me/{{ $whatsappDigits }}" target="_blank" rel="noopener">{{ $contactWhatsapp }}</a>
                    <a class="contact-action" href="https://wa.me/{{ $whatsappDigits }}?text={{ urlencode($contactSubject) }}" target="_blank" rel="noopener">Chatvia WhatsApp <span aria-hidden="true">↗</span></a>
                </div>
            </article>
        @endif

        @if ($phoneDigits)
            <article class="contact-item">
                <span class="contact-icon" aria-hidden="true">☎</span>
                <div>
                    <span class="contact-label">TELEPON</span>
                    <a class="contact-value" href="tel:{{ $phoneDigits }}">{{ $contactPhone }}</a>
                    <span class="contact-action is-static">Hubungi via telepon</span>
                </div>
            </article>
        @endif

        @if ($contactAddress)
            <article class="contact-item">
                <span class="contact-icon" aria-hidden="true">⌖</span>
                <div>
                    <span class="contact-label">ALAMAT</span>
                    @if ($contactMaps)
                        <a class="contact-value" href="{{ $contactMaps }}" target="_blank" rel="noopener">{{ $contactAddress }}</a>
                    @else
                        <span class="contact-value">{{ $contactAddress }}</span>
                    @endif
                </div>
            </article>
        @endif

        @if ($contactInstagram)
            <article class="contact-item">
                <span class="contact-icon" aria-hidden="true">◎</span>
                <div>
                    <span class="contact-label">INSTAGRAM</span>
                    <a class="contact-value" href="https://instagram.com/{{ ltrim($contactInstagram, '@') }}" target="_blank" rel="noopener">{{ $contactInstagram }}</a>
                    <span class="contact-action is-static">Lihat profil kami</span>
                </div>
            </article>
        @endif

        @if ($contactHours)
            <article class="contact-item">
                <span class="contact-icon" aria-hidden="true">◷</span>
                <div>
                    <span class="contact-label">JAM LAYANAN</span>
                    <span class="contact-value">{{ $contactHours }}</span>
                </div>
            </article>
        @endif
    </div>

    <div class="contact-foot">
        <p>Untuk sponsorship dan iklan, sebutkan jenis paket Anda agar kami bisa kirim pricelist.</p>
        <a class="public-button public-button-orange" href="mailto:{{ $contactEmail }}?subject={{ urlencode('Sponsor dan Iklan Inlinesia') }}">Tanya sponsorship <span aria-hidden="true">→</span></a>
    </div>
</section>