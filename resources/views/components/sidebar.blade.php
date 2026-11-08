<aside class="sidebar">
    <button
        class="sidebar-toggle"
        type="button"
        data-sidebar-toggle
        aria-label="Sembunyikan sidebar"
        aria-controls="primary-admin-navigation"
        aria-expanded="true"
    >
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="m14 5-7 7 7 7" />
        </svg>
    </button>
    <a class="brand" href="{{ route('admin.dashboard') }}" aria-label="Inlinesia, panel panitia">
        <x-logo variant="admin" />
    </a>

    <div class="workspace-label">WORKSPACE</div>
    <nav id="primary-admin-navigation" class="primary-nav" aria-label="Navigasi admin">
        <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}" href="{{ route('admin.dashboard') }}" aria-label="Beranda">
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/></svg>
            <span>Beranda</span>
        </a>
        <a class="nav-link {{ request()->routeIs('admin.event-submissions.*') ? 'is-active' : '' }}" href="{{ route('admin.event-submissions.index') }}" aria-label="Pengajuan event">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.5h16v13H4zM8 9h8m-8 3h8m-8 3h5"/></svg>
            <span>Pengajuan event</span>
            @php($pendingEventCount = \App\Models\Event::query()->where('status', 'pending')->count())
            @if ($pendingEventCount > 0)<span class="nav-count">{{ $pendingEventCount > 99 ? '99+' : $pendingEventCount }}</span>@endif
        </a>
        <a class="nav-link {{ request()->routeIs('admin.accounts.*') ? 'is-active' : '' }}" href="{{ route('admin.accounts.index') }}" aria-label="Kelola akun">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3.5 19v-1.5a5.5 5.5 0 0 1 11 0V19zm13-10h5m-2.5-2.5v5"/></svg>
            <span>Kelola akun</span>
        </a>
        <a class="nav-link {{ request()->routeIs('admin.settings.*') ? 'is-active' : '' }}" href="{{ route('admin.settings.theme.edit') }}" aria-label="Setting">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M12 4a8 8 0 0 1 0 16"/></svg>
            <span>Setting</span>
        </a>
        <a class="nav-link {{ request()->routeIs('admin.data.backup*', 'admin.data.restore') ? 'is-active' : '' }}" href="{{ route('admin.data.backup') }}" aria-label="Backup dan restore">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 15V3m0 0L8 7m4-4 4 4M5 13v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6"/></svg>
            <span>Backup &amp; restore</span>
        </a>
        <a class="nav-link {{ request()->routeIs('admin.data.delete*') ? 'is-active' : '' }}" href="{{ route('admin.data.delete') }}" aria-label="Hapus data">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16m-10 4v6m4-6v6M6 7l1 14h10l1-14M9 7V4h6v3"/></svg>
            <span>Hapus data</span>
        </a>
        <a class="nav-link nav-public-home" href="{{ route('home') }}" aria-label="Kembali ke beranda utama">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/></svg>
            <span>Beranda utama</span><span class="nav-external" aria-hidden="true">↗</span>
        </a>
    </nav>

    <div class="sidebar-bottom">
        <div class="user-profile">
            <span class="avatar">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(auth()->user()->name, 0, 1)) }}</span>
            <span class="user-copy"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->roleLabel() }}</small></span>
            <a class="user-menu" href="{{ route('profile.show') }}" aria-label="Buka profil">•••</a>
        </div>
    </div>
</aside>
