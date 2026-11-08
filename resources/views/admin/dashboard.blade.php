@extends('layouts.admin')

@section('title', 'Panel Event — Inlinesia')
@section('section', 'Beranda')

@section('content')
<div class="page-content">
                <section class="welcome-row">
                    <div>
                        <div class="eyebrow"><span class="eyebrow-line"></span> {{ now()->translatedFormat('l, d F Y') }}</div>
                        <h1>Selamat datang, Panitia <span class="wave">✳</span></h1>
                        <p class="page-subtitle">Pantau event sepatu roda yang dikelola tim Anda dari satu tempat.</p>
                    </div>
                    <a class="button button-primary" href="{{ route('guest.dashboard') }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14m-7-7h14"/></svg>
                        Kelola event
                    </a>
                </section>

                <section class="hero-banner" aria-label="Ringkasan musim lomba">
                    <div class="hero-copy">
                        <span class="hero-kicker"><span></span> MUSIM LOMBA 2026</span>
                        <h2>Satu musim,<br>lebih banyak prestasi.</h2>
                        <p>Kelola kejuaraan, atlet, dan hasil lomba dengan lebih rapi—dari pendaftaran sampai podium.</p>
                        <a href="#kejuaraan" class="hero-link">Jelajahi kejuaraan <span aria-hidden="true">↗</span></a>
                    </div>
                    <div class="hero-art" aria-hidden="true">
                        <div class="art-orbit orbit-one"></div>
                        <div class="art-orbit orbit-two"></div>
                        <div class="art-sun"></div>
                        <div class="art-number">26<span>·</span>06</div>
                        <div class="art-track"><i></i><i></i><i></i><i></i><i></i></div>
                        <div class="art-caption">KEDIRI<br>ON WHEELS</div>
                    </div>
                    <span class="hero-decoration decoration-one"></span>
                    <span class="hero-decoration decoration-two"></span>
                </section>

                <section class="stats-grid" aria-label="Ringkasan data">
                    <article class="stat-card">
                        <div class="stat-top"><span class="stat-label">KEJUARAAN AKTIF</span><span class="stat-icon stat-purple">✳</span></div>
                        <div class="stat-value">{{ str_pad((string) $activeEventCount, 2, '0', STR_PAD_LEFT) }}</div>
                        <div class="stat-foot">Event disetujui yang belum selesai</div>
                        <span class="stat-spark spark-purple" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i></span>
                    </article>
                    <article class="stat-card">
                        <div class="stat-top"><span class="stat-label">ATLET TERDAFTAR</span><span class="stat-icon stat-blue">↗</span></div>
                        <div class="stat-value">{{ $athleteCount }}</div>
                        <div class="stat-foot">Pendaftaran pada event aktif</div>
                        <span class="stat-spark spark-blue" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i></span>
                    </article>
                    <article class="stat-card">
                        <div class="stat-top"><span class="stat-label">KLUB BERPARTISIPASI</span><span class="stat-icon stat-orange">⌁</span></div>
                        <div class="stat-value">{{ $clubCount }}</div>
                        <div class="stat-foot">Klub terwakili pada event aktif</div>
                        <span class="stat-spark spark-orange" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i></span>
                    </article>
                    <article class="stat-card">
                        <div class="stat-top"><span class="stat-label">PENGAJUAN EVENT PERLU CEK</span><span class="stat-icon stat-green">✓</span></div>
                        <div class="stat-value">{{ $pendingApplicationCount }}</div>
                        <div class="stat-foot"><a href="{{ route('admin.event-submissions.index') }}">Tinjau pengajuan <span aria-hidden="true">→</span></a></div>
                        <span class="stat-spark spark-green" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i></span>
                    </article>
                </section>

                <div class="content-grid">
                    <section class="panel events-panel" id="kejuaraan">
                        <div class="panel-heading">
                            <div>
                                <span class="section-kicker">KALENDER KOMPETISI</span>
                                <h2>Kejuaraan mendatang</h2>
                            </div>
                            <a class="text-link" href="{{ route('events.index') }}">Kelola event <span aria-hidden="true">→</span></a>
                        </div>
                        <div class="event-list">
                            @forelse ($events as $event)
                                <article class="event-row">
                                    <div class="event-date"><strong>{{ $event->start_date->format('d') }}</strong><span>{{ strtoupper($event->start_date->translatedFormat('M')) }}</span></div>
                                    <div class="event-info">
                                        <div class="event-name-line"><h3>{{ $event->name }}</h3>
                                            @if ($event->registration_deadline && $event->registration_deadline->endOfDay()->isPast())
                                                <span class="status status-draft">Pendaftaran ditutup</span>
                                            @else
                                                <span class="status status-open">Disetujui</span>
                                            @endif
                                        </div>
                                        <p><span class="event-pin">⌖</span> {{ $event->venue }}, {{ $event->venue_address }} <span class="event-divider">·</span> <span>{{ count($event->races) }} mata lomba</span></p>
                                    </div>
                                    <div class="event-participants"><strong>{{ $event->registrations_count }}</strong><span>atlet</span></div>
                                    <span class="row-arrow" aria-hidden="true">↗</span>
                                </article>
                            @empty
                                <div class="guest-dashboard-empty"><strong>Belum ada kejuaraan aktif.</strong><p>Event akan tampil setelah disetujui Admin.</p></div>
                            @endforelse
                        </div>
                        <div class="panel-footer"><span class="demo-dot"></span> Data kejuaraan dan pendaftaran terbaru</div>
                    </section>

                    <aside class="panel right-panel" id="peserta">
                        <div class="panel-heading">
                            <div>
                                <span class="section-kicker">YANG PERLU DITINDAKLANJUTI</span>
                                <h2>Agenda panitia</h2>
                            </div>
                            <span class="agenda-count">{{ $pendingApplicationCount }}</span>
                        </div>
                        <div class="agenda-list">
                            @if ($pendingApplicationCount > 0)
                                <article class="agenda-item">
                                    <span class="agenda-marker marker-violet"></span>
                                    <div><strong>{{ $pendingApplicationCount }} pengajuan menunggu tinjauan</strong><p>Periksa data event dari pengelola.</p><a class="agenda-time" href="{{ route('admin.event-submissions.index') }}">BUKA PENGAJUAN →</a></div>
                                </article>
                            @else
                                <div class="guest-dashboard-empty"><strong>Tidak ada pengajuan tertunda.</strong><p>Pengajuan baru akan muncul setelah dikirim pengelola.</p></div>
                            @endif
                        </div>
                        <a class="agenda-link" href="{{ route('events.index') }}">Lihat kejuaraan aktif <span aria-hidden="true">→</span></a>
                    </aside>
                </div>

                <section class="module-strip" id="perlombaan">
                    <div class="module-intro"><span class="section-kicker">SATU TEMPAT UNTUK SEMUANYA</span><h2>Semua bagian lomba, saling terhubung.</h2></div>
                    <div class="module-list">
                        <div class="module-item" id="hasil"><span class="module-icon module-medal">★</span><span><strong>Hasil &amp; podium</strong><small>Catat hasil dan juara</small></span><span class="module-arrow">↗</span></div>
                        <div class="module-item"><span class="module-icon module-race">⌁</span><span><strong>Mata lomba &amp; kategori</strong><small>Atur nomor dan kelompok usia</small></span><span class="module-arrow">↗</span></div>
                        <div class="module-item" id="keuangan"><span class="module-icon module-money">Rp</span><span><strong>Keuangan</strong><small>Biaya, pembayaran, laporan</small></span><span class="module-arrow">↗</span></div>
                    </div>
                </section>

                <footer class="page-footer"><span>© {{ now()->year }} Inlinesia</span><span>Operasional event sepatu roda <span class="footer-heart">♥</span></span></footer>
</div>
@endsection
