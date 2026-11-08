@props(['section' => 'Beranda'])

<header class="topbar">
    <div class="breadcrumbs"><a href="{{ route('admin.dashboard') }}">Workspace</a><span class="crumb-separator">/</span><strong>{{ $section }}</strong></div>
    <div class="topbar-actions">
        <span class="season-pill"><span class="season-dot"></span> MUSIM 2026</span>
        <a class="avatar avatar-small" href="{{ route('profile.show') }}" aria-label="Profil {{ auth()->user()->name }}">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(auth()->user()->name, 0, 1)) }}</a>
    </div>
</header>
