@auth
<div class="user-badge" data-user-badge>
    <span class="user-badge-avatar" aria-hidden="true">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
    <span class="user-badge-text">
        <span class="user-badge-name">{{ auth()->user()->name }}</span>
        <span class="user-badge-role">{{ auth()->user()->role }}</span>
    </span>
</div>
@endauth
@guest
<div class="user-badge user-badge-guest" data-user-badge>
    <span class="user-badge-avatar" aria-hidden="true">?</span>
    <span class="user-badge-text">
        <span class="user-badge-name">Tamu</span>
        <span class="user-badge-role">guest</span>
    </span>
</div>
@endguest
