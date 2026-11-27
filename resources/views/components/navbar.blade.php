<header class="public-header{{ auth()->check() && (auth()->user()->role === 'admin' || auth()->user()->hasVerifiedEmail()) ? ' public-header-account' : '' }}">
    <a class="public-brand" href="{{ route('home') }}#beranda" aria-label="Inlinesia, beranda">
        <x-logo />
    </a>

    <nav class="public-nav{{ auth()->check() && (auth()->user()->role === 'admin' || auth()->user()->hasVerifiedEmail()) ? ' is-account-nav' : '' }}{{ request()->routeIs('home') ? '' : ' is-minimal' }}" aria-label="Navigasi utama">
        <a href="{{ route('home') }}" @if (request()->routeIs('home')) class="is-current" aria-current="page" @endif>Beranda utama</a>
        @if (request()->routeIs('home'))
            <a href="{{ route('home') }}#kejuaraan" @if (request()->routeIs('events.index')) class="is-current" aria-current="page" @endif>Daftar kejuaraan</a>
            <a href="{{ route('home') }}#layanan">Fitur platform</a>
            <a href="{{ route('home') }}#layanan">Alur penggunaan</a>
            <a href="{{ route('home') }}#kontak">Kontak</a>
            <a href="{{ route('home') }}#tentang">Tentang kami</a>
        @endif
    </nav>

    @if ($mainWebSponsors->isNotEmpty())
        <div class="public-header-sponsors" aria-label="Sponsor utama">
            @foreach ($mainWebSponsors as $sponsor)
                <a class="sponsor-web-main-logo" href="{{ $sponsor->website_url }}" target="_blank" rel="noopener noreferrer" title="{{ $sponsor->brand_name }}">
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($sponsor->logo_path) }}" alt="{{ $sponsor->brand_name }}">
                </a>
            @endforeach
        </div>
    @endif

    <div class="public-header-actions">
        @auth
            <a class="public-login" href="{{ route('profile.show') }}">Profil</a>
            @if (auth()->user()->role === 'admin' || auth()->user()->hasVerifiedEmail())
                <a class="public-button public-button-orange" href="{{ route('guest.dashboard') }}">Buka dasbor <span aria-hidden="true">↗</span></a>
                <a class="public-login" href="{{ route('event-applications.create') }}">Daftarkan event</a>
            @endif
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="public-login public-logout" type="submit">Keluar</button>
            </form>
        @else
            <a class="public-login" href="{{ route('login') }}">Masuk</a>
            <a class="public-button public-button-dark" href="{{ route('register') }}">Buat akun <span aria-hidden="true">↗</span></a>
        @endauth
    </div>
</header>
