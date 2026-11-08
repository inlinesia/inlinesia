@if(auth()->check() && auth()->user()->role === 'admin')
@php
    $bladePath = str_replace('.', '/', $bladeLocation ?? '');
    $bladeFile = $bladePath ? $bladePath . '.blade.php' : 'unknown';
@endphp
<div class="blade-location" data-blade-location>
    <span class="blade-location-icon" aria-hidden="true">&#128196;</span>
    <span class="blade-location-path">{{ $bladeFile }}</span>
</div>
@endif
