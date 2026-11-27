<footer class="public-footer">
    <a class="public-brand footer-brand" href="{{ route('home') }}#beranda">
        <x-logo />
    </a>
    @if ($mainWebSponsors->isNotEmpty())
        <div class="public-footer-sponsors" aria-label="Didukung oleh">
            <span>Didukung oleh</span>
            @foreach ($mainWebSponsors as $sponsor)
                <a class="sponsor-web-main-logo" href="{{ $sponsor->website_url }}" target="_blank" rel="noopener noreferrer" title="{{ $sponsor->brand_name }}">
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($sponsor->logo_path) }}" alt="{{ $sponsor->brand_name }}">
                </a>
            @endforeach
        </div>
    @endif
    <span class="footer-tagline">Event sepatu roda, dari ide sampai garis finis.</span>
    <span class="footer-copyright">
        © 2026 - Damai digital creative
        <a class="footer-license-link" href="{{ route('license') }}">Lisensi</a>
    </span>
</footer>
