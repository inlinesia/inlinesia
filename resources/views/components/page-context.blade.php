{{-- Widget mengambang: user-badge untuk semua role + lokasi blade untuk admin. --}}
@php
    $showBladeLocation = auth()->check() && auth()->user()->role === 'admin';
@endphp

<div class="page-context" data-page-context>
    <div class="page-context-card" data-page-context-card>
        <div class="page-context-head">
            <span class="page-context-title">{{ $showBladeLocation ? 'Panel Pengembang' : 'Status Akun' }}</span>
            <button
                type="button"
                class="page-context-toggle"
                data-page-context-toggle
                aria-expanded="true"
                aria-controls="page-context-body"
                title="Sembunyikan / tampilkan"
            >–</button>
        </div>

        <div class="page-context-body" id="page-context-body" data-page-context-body>
            <x-user-badge />

            @if ($showBladeLocation)
                <x-blade-location />
            @endif
        </div>
    </div>
</div>