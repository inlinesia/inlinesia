@extends($isAdmin ? 'layouts.admin' : 'layouts.public')

@section('title', 'Keuangan event — '.$event->name)
@section('section', 'Kelola event')
@section('description', 'Ringkasan pemasukan dan pengeluaran '.$event->name.'.')
@section('body-class', 'auth-page')

@section('content')
<main class="public-application-page event-finance-page">
    <section class="event-form-heading">
        <div>
            <div class="account-eyebrow">{{ $isAdmin ? 'PENGELOLAAN ADMIN' : 'KEUANGAN KEJUARAAN' }}</div>
            <h1>Keuangan event</h1>
            <p class="page-subtitle">{{ $event->name }} · {{ $event->start_date->format('d/m/Y') }}–{{ $event->end_date->format('d/m/Y') }} · {{ $event->venue }}</p>
        </div>
        <a class="form-back-link" href="{{ route('guest.dashboard') }}">← Kembali ke kelola event</a>
    </section>

    @if (session('status'))
        <div class="public-flash" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="form-errors" role="alert">
            <strong>Catatan keuangan belum tersimpan.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- Kartu ringkasan --}}
    <section class="finance-summary" aria-label="Ringkasan keuangan">
        <article class="finance-summary-card">
            <span class="finance-summary-label">Pemasukan pendaftaran</span>
            <strong class="finance-summary-value">{{ $registrationIncome ? 'Rp '.number_format($registrationIncome, 0, ',', '.') : 'Rp 0' }}</strong>
            <small class="finance-summary-foot">Dari {{ $paidCount }} pembayaran terkonfirmasi
                @if ($registrationRefunds > 0)
                    · refund {{ 'Rp '.number_format($registrationRefunds, 0, ',', '.') }}
                @endif
                @if ($pendingIncome > 0)
                    · <span class="finance-summary-pending">Rp {{ number_format($pendingIncome, 0, ',', '.') }} masih menunggu</span>
                @endif
            </small>
            @if ($feesUncalculated)
                <small class="finance-summary-warn">Nominal tarif belum dihitung untuk {{ $paidCount }} peserta ini, jadi belum ada yang bisa dijumlahkan.</small>
            @endif
        </article>
        <article class="finance-summary-card">
            <span class="finance-summary-label">Pemasukan lain</span>
            <strong class="finance-summary-value">{{ $otherIncome ? 'Rp '.number_format($otherIncome, 0, ',', '.') : 'Rp 0' }}</strong>
            <small class="finance-summary-foot">Sponsor, merchandise, donasi
                @if ($sponsorIncome > 0)· <span>{{ number_format($sponsorIncome, 0, ',', '.') }} dari sponsor</span>@endif
            </small>
        </article>
        <article class="finance-summary-card">
            <span class="finance-summary-label">Pengeluaran</span>
            <strong class="finance-summary-value">{{ $expenses ? 'Rp '.number_format($expenses, 0, ',', '.') : 'Rp 0' }}</strong>
            <small class="finance-summary-foot">{{ $transactions->where('type', 'expense')->count() }} catatan pengeluaran</small>
        </article>
        <article class="finance-summary-card {{ $balance >= 0 ? 'is-positive' : 'is-negative' }}">
            <span class="finance-summary-label">Saldo</span>
            <strong class="finance-summary-value">{{ 'Rp '.number_format(abs($balance), 0, ',', '.') }}</strong>
            <small class="finance-summary-foot">{{ $balance >= 0 ? 'Surplus' : 'Defisit' }} · total pemasukan Rp {{ number_format($totalIncome, 0, ',', '.') }}</small>
        </article>
    </section>

    {{-- Grafik bulanan --}}
    <section class="finance-chart" aria-label="Grafik pemasukan, refund, dan pengeluaran enam bulan terakhir">
        <div class="finance-chart-heading">
            <div>
                <span class="form-step">01</span>
                <h2>Pergerakan enam bulan terakhir</h2>
            </div>
            <div class="finance-chart-legend">
                <span><i class="finance-swatch is-income" aria-hidden="true"></i>Pemasukan</span>
                <span><i class="finance-swatch is-refund" aria-hidden="true"></i>Refund peserta</span>
                <span><i class="finance-swatch is-expense" aria-hidden="true"></i>Pengeluaran</span>
            </div>
        </div>
        @php($peak = max(1, collect($chart)->max(fn ($m) => max($m['income'], $m['refund'], $m['expense']))))
        <div class="finance-chart-bars">
            @foreach ($chart as $month)
                <div class="finance-chart-column" title="{{ $month['full'] }}: pemasukan {{ number_format($month['income'], 0, ',', '.') }}, refund {{ number_format($month['refund'], 0, ',', '.') }}, pengeluaran {{ number_format($month['expense'], 0, ',', '.') }}">
                    <div class="finance-chart-pair">
                        <span class="finance-bar is-income" style="height: {{ $month['income'] > 0 ? max(3, round($month['income'] / $peak * 100)) : 0 }}%"></span>
                        <span class="finance-bar is-refund" style="height: {{ $month['refund'] > 0 ? max(3, round($month['refund'] / $peak * 100)) : 0 }}%"></span>
                        <span class="finance-bar is-expense" style="height: {{ $month['expense'] > 0 ? max(3, round($month['expense'] / $peak * 100)) : 0 }}%"></span>
                    </div>
                    <span class="finance-chart-label">{{ $month['label'] }}</span>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Rincian pengeluaran per kategori --}}
    @if ($expenseBreakdown->isNotEmpty())
        <section class="finance-breakdown" aria-label="Rincian pengeluaran per kategori">
            <div class="finance-chart-heading">
                <div>
                    <span class="form-step">02</span>
                    <h2>Pengeluaran per kategori</h2>
                </div>
            </div>
            <ul class="finance-breakdown-list">
                @php($breakdownPeak = max(1, $expenseBreakdown->max('total')))
                @foreach ($expenseBreakdown as $row)
                    <li>
                        <span class="finance-breakdown-label">{{ $row['label'] }}</span>
                        <span class="finance-breakdown-track"><i style="width: {{ max(2, round($row['total'] / $breakdownPeak * 100)) }}%"></i></span>
                        <span class="finance-breakdown-total">Rp {{ number_format($row['total'], 0, ',', '.') }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Tambah catatan --}}
    <section class="form-section">
        <div class="form-section-heading">
            <span class="form-step">03</span>
            <div><h2>Catat pemasukan atau pengeluaran</h2><p>Pemasukan pendaftaran tidak dicatat di sini karena sudah dihitung otomatis dari pembayaran peserta.</p></div>
        </div>
        <form class="finance-form form-fields" method="POST" action="{{ route('owner.events.finance.store', $event) }}" data-finance-form>
            @csrf
            <div class="form-field">
                <label for="finance-type">Jenis <span>*</span></label>
                <select id="finance-type" name="type" required data-finance-type>
                    @foreach (['income' => 'Pemasukan', 'expense' => 'Pengeluaran'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="finance-category">Kategori <span>*</span></label>
                <select id="finance-category" name="category" required data-finance-category>
                    @foreach (\App\Models\EventFinanceTransaction::categoriesFor(old('type', 'income')) as $value => $label)
                        <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field form-field-full">
                <label for="finance-description">Keterangan <span>*</span></label>
                <input id="finance-description" name="description" value="{{ old('description') }}" maxlength="180" required placeholder="Contoh: Sponsor utama BCA">
            </div>
            <div class="form-field">
                <label for="finance-amount">Nominal (Rp) <span>*</span></label>
                <input id="finance-amount" name="amount" type="number" min="1" step="1" inputmode="numeric" value="{{ old('amount') }}" required placeholder="2500000">
            </div>
            <div class="form-field">
                <label for="finance-date">Tanggal <span>*</span></label>
                <input id="finance-date" name="occurred_on" type="date" max="{{ today()->toDateString() }}" value="{{ old('occurred_on', today()->toDateString()) }}" required>
            </div>
            <div class="form-field form-field-full">
                <label for="finance-notes">Catatan <span class="optional-label">opsional</span></label>
                <input id="finance-notes" name="notes" value="{{ old('notes') }}" maxlength="255" placeholder="Nominal persetujuan, nomor invoice, dan sejenisnya">
            </div>
            <div class="form-field form-field-full finance-form-actions">
                <button class="account-submit" type="submit">Simpan catatan <span aria-hidden="true">→</span></button>
            </div>
        </form>
    </section>

    <section class="form-section">
        <div class="form-section-heading">
            <span class="form-step">04</span>
            <div><h2>Riwayat pembayaran dan refund peserta</h2><p>Setiap pelunasan selisih tarif dan pengembalian dana tercatat di sini.</p></div>
        </div>
        @if ($registrationEntries->isEmpty())
            <p class="finance-summary-foot">Belum ada riwayat pembayaran peserta yang dicatat sejak pencatatan transaksi diaktifkan.</p>
        @else
            <div class="finance-table-wrap">
                <table class="account-table finance-table">
                    <caption class="sr-only">Riwayat pembayaran dan refund peserta</caption>
                    <thead>
                        <tr>
                            <th scope="col">Tanggal</th>
                            <th scope="col">Peserta</th>
                            <th scope="col">Keterangan</th>
                            <th scope="col" class="finance-numeric">Nominal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($registrationEntries as $entry)
                            <tr class="finance-row is-{{ $entry->type === 'payment' ? 'income' : 'expense' }}">
                                <td data-label="Tanggal">{{ $entry->occurred_at->format('d/m/Y') }}</td>
                                <td data-label="Peserta">{{ $entry->registration?->participant_name ?? 'Pendaftaran dihapus' }}</td>
                                <td data-label="Keterangan">{{ $entry->description }}</td>
                                <td data-label="Nominal" class="finance-numeric">{{ $entry->type === 'payment' ? '+' : '−' }} Rp {{ number_format($entry->amount, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- Daftar catatan --}}
    <section class="form-section">
        <div class="form-section-heading">
            <span class="form-step">05</span>
            <div><h2>Riwayat catatan</h2><p>{{ $transactions->count() }} catatan manual, di luar pemasukan pendaftaran yang dihitung otomatis.</p></div>
        </div>

        @if ($transactions->isEmpty())
            <div class="listing-empty-state">
                <span aria-hidden="true">◷</span>
                <h3>Belum ada catatan keuangan</h3>
                <p>Catat sponsor atau pengeluaran di atas supaya saldo event bisa dipantau.</p>
            </div>
        @else
            <div class="finance-table-wrap">
                <table class="account-table finance-table">
                    <caption class="sr-only">Daftar catatan pemasukan dan pengeluaran manual</caption>
                    <thead>
                        <tr>
                            <th scope="col">Tanggal</th>
                            <th scope="col">Keterangan</th>
                            <th scope="col">Kategori</th>
                            <th scope="col" class="finance-numeric">Nominal</th>
                            <th scope="col"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transactions as $transaction)
                            <tr class="finance-row is-{{ $transaction->type }}">
                                <td data-label="Tanggal">{{ $transaction->occurred_on->format('d/m/Y') }}</td>
                                <td data-label="Keterangan">
                                    <strong>{{ $transaction->description }}</strong>
                                    @if ($transaction->notes)<small>{{ $transaction->notes }}</small>@endif
                                </td>
                                <td data-label="Kategori"><span class="finance-badge is-{{ $transaction->type }}">{{ $transaction->categoryLabel() }}</span></td>
                                <td data-label="Nominal" class="finance-numeric">{{ $transaction->isIncome() ? '+' : '−' }} Rp {{ number_format((int) $transaction->amount, 0, ',', '.') }}</td>
                                <td class="finance-row-actions">
                                    <details class="finance-edit">
                                        <summary>Ubah</summary>
                                        <form class="finance-edit-form" method="POST" action="{{ route('owner.events.finance.update', [$event, $transaction]) }}">
                                            @csrf
                                            @method('PATCH')
                                            <label for="edit-{{ $transaction->id }}-description">Keterangan</label>
                                            <input id="edit-{{ $transaction->id }}-description" name="description" value="{{ $transaction->description }}" maxlength="180" required>
                                            <div class="finance-edit-pair">
                                                <span>
                                                    <label for="edit-{{ $transaction->id }}-amount">Nominal (Rp)</label>
                                                    <input id="edit-{{ $transaction->id }}-amount" name="amount" type="number" min="1" step="1" inputmode="numeric" value="{{ (int) $transaction->amount }}" required>
                                                </span>
                                                <span>
                                                    <label for="edit-{{ $transaction->id }}-date">Tanggal</label>
                                                    <input id="edit-{{ $transaction->id }}-date" name="occurred_on" type="date" max="{{ today()->toDateString() }}" value="{{ $transaction->occurred_on->toDateString() }}" required>
                                                </span>
                                            </div>
                                            <label for="edit-{{ $transaction->id }}-notes">Catatan</label>
                                            <input id="edit-{{ $transaction->id }}-notes" name="notes" value="{{ $transaction->notes }}" maxlength="255">
                                            <input type="hidden" name="type" value="{{ $transaction->type }}">
                                            <input type="hidden" name="category" value="{{ $transaction->category }}">
                                            <button class="account-submit" type="submit">Simpan perubahan</button>
                                        </form>
                                    </details>
                                    <form class="finance-delete" method="POST" action="{{ route('owner.events.finance.destroy', [$event, $transaction]) }}"
                                          data-confirm="Hapus catatan &quot;{{ $transaction->description }}&quot;?">
                                        @csrf
                                        @method('DELETE')
                                        <button class="finance-delete-button" type="submit">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</main>
@endsection

@push('scripts')
<script>
    (function () {
        var form = document.querySelector('[data-finance-form]');
        if (!form) return;
        var typeSelect = form.querySelector('[data-finance-type]');
        var categorySelect = form.querySelector('[data-finance-category]');
        var labels = {
            income: @json(\App\Models\EventFinanceTransaction::INCOME_CATEGORIES),
            expense: @json(\App\Models\EventFinanceTransaction::EXPENSE_CATEGORIES)
        };

        function fillCategories() {
            var chosen = categorySelect.value;
            categorySelect.innerHTML = '';
            Object.keys(labels[typeSelect.value]).forEach(function (key) {
                var option = document.createElement('option');
                option.value = key;
                option.textContent = labels[typeSelect.value][key];
                if (key === chosen) option.selected = true;
                categorySelect.appendChild(option);
            });
        }

        typeSelect.addEventListener('change', fillCategories);
    })();
</script>
@endpush