<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventFinanceTransaction;
use App\Models\EventRegistration;
use App\Models\EventRegistrationFinancialEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EventFinanceController extends Controller
{
    /**
     * Ringkasan pemasukan, pengeluaran, dan saldo sebuah event, ditambah daftar
     * catatan manual dan deretan bulanan untuk grafik.
     *
     * Pemasukan pendaftaran dihitung dari fee_total pada pendaftaran yang
     * pembayarannya sudah dikonfirmasi — bukan dari tabel transaksi — supaya
     * tidak mungkin melenceng dari data peserta yang sebenarnya.
     */
    public function index(Request $request, Event $event): View
    {
        $isAdmin = $this->authorizeManager($request, $event);

        $registrationIncome = $event->confirmedRegistrationIncome();

        $paidCount = $event->registrations()
            ->where('payment_status', 'paid')
            ->count();

        $pendingIncome = (int) $event->registrations()
            ->where('payment_status', 'pending')
            ->where(function ($query): void {
                $query->whereNull('cancellation_status')
                    ->orWhere('cancellation_status', 'rejected');
            })
            ->withSum(['financialEntries as amount_collected' => fn ($query) => $query->where('type', 'payment')], 'amount')
            ->withSum(['financialEntries as amount_refunded' => fn ($query) => $query->where('type', 'refund')], 'amount')
            ->get(['id', 'fee_total'])
            ->sum(fn (EventRegistration $registration): int => max(
                0,
                (int) $registration->fee_total
                    - (int) $registration->amount_collected
                    + (int) $registration->amount_refunded,
            ));

        $registrationRefunds = (int) $event->registrationFinancialEntries()
            ->where('type', 'refund')
            ->sum('amount')
            + (int) $event->registrations()
                ->whereDoesntHave('financialEntries')
                ->sum('refund_amount');

        /*
         * Pembayaran bisa sudah dikonfirmasi tanpa nominal biaya terisi —
         * misalnya peserta didaftarkan tanpa tarif kategori, atau data lama
         * diimpor tanpa perhitungan tarif. Kalau itu terjadi, kartu
         * "Pemasukan pendaftaran" akan menampilkan Rp 0 padahal ada peserta
         * yang sudah membayar, sehingga perlu penjelasan di halaman.
         */
        $feesUncalculated = $paidCount > 0 && $registrationIncome === 0;

        $manual = $event->financeTransactions();

        $incomeRows = (clone $manual)->where('type', 'income')->get();
        $expenseRows = (clone $manual)->where('type', 'expense')->get();

        $sponsorIncome = (int) $incomeRows
            ->where('category', 'sponsor')
            ->sum(fn (EventFinanceTransaction $t): float => (float) $t->amount);

        $otherIncome = (int) $incomeRows->sum(fn (EventFinanceTransaction $t): float => (float) $t->amount);
        $expenses = (int) $expenseRows->sum(fn (EventFinanceTransaction $t): float => (float) $t->amount);

        $totalIncome = $registrationIncome + $otherIncome;
        $balance = $totalIncome - $expenses;

        return view('verified.events.finance.index', [
            'isAdmin' => $isAdmin,
            'event' => $event,
            'registrationIncome' => $registrationIncome,
            'registrationRefunds' => $registrationRefunds,
            'paidCount' => $paidCount,
            'feesUncalculated' => $feesUncalculated,
            'pendingIncome' => $pendingIncome,
            'otherIncome' => $otherIncome,
            'sponsorIncome' => $sponsorIncome,
            'expenses' => $expenses,
            'totalIncome' => $totalIncome,
            'balance' => $balance,
            'transactions' => $event->financeTransactions()
                ->with('author:id,name')
                ->orderByDesc('occurred_on')
                ->orderByDesc('id')
                ->get(),
            'registrationEntries' => $event->registrationFinancialEntries()
                ->with('registration:id,participant_name')
                ->orderByDesc('occurred_at')
                ->get(),
            'expenseBreakdown' => $expenseRows
                ->groupBy('category')
                ->map(fn ($rows, $category): array => [
                    'label' => EventFinanceTransaction::EXPENSE_CATEGORIES[$category] ?? 'Lainnya',
                    'total' => (int) $rows->sum(fn (EventFinanceTransaction $t): float => (float) $t->amount),
                ])
                ->sortByDesc('total')
                ->values(),
            'chart' => $this->monthlySeries($event),
        ]);
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeManager($request, $event);

        $data = $this->validated($request);

        $event->financeTransactions()->create($data + ['created_by' => $request->user()->id]);

        return back()->with('status', 'Catatan keuangan tersimpan.');
    }

    public function update(Request $request, Event $event, EventFinanceTransaction $transaction): RedirectResponse
    {
        $this->authorizeManager($request, $event);

        abort_unless($transaction->event_id === $event->id, 404);

        $transaction->update($this->validated($request, $transaction));

        return back()->with('status', 'Catatan keuangan diperbarui.');
    }

    public function destroy(Request $request, Event $event, EventFinanceTransaction $transaction): RedirectResponse
    {
        $this->authorizeManager($request, $event);

        abort_unless($transaction->event_id === $event->id, 404);

        $transaction->delete();

        return back()->with('status', 'Catatan keuangan dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?EventFinanceTransaction $transaction = null): array
    {
        $isIncome = $request->input('type') === 'income';

        $data = $request->validate([
            'type' => ['required', Rule::in(['income', 'expense'])],
            'category' => [
                'required',
                Rule::in(array_keys($isIncome
                    ? EventFinanceTransaction::INCOME_CATEGORIES
                    : EventFinanceTransaction::EXPENSE_CATEGORIES)),
            ],
            'description' => ['required', 'string', 'max:180'],
            'amount' => ['required', 'numeric', 'min:1', 'max:99999999999.99'],
            'occurred_on' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        return [
            ...$data,
            'amount' => (int) round((float) $data['amount']),
            'occurred_on' => Carbon::parse($data['occurred_on'])->toDateString(),
        ];
    }

    /**
     * Enam bulan yang berakhir pada bulan event — atau bulan ini, mana yang
     * lebih akhir.
     *
     * Jendelanya sengaja mengikuti jadwal event, bukan "enam bulan terakhir
     * dari hari ini". Event yang akan datang empat bulan ke depan sudah
     * menerima sponsor dan membayar Sewa lokasi bulan ini juga; kalau jendela
     * dipakai berdasarkan tanggal hari ini, grafiknya kosong padahal uangnya
     * sudah keluar. Untuk event yang sudah lewat, jendela berakhir di bulan
     * event.
     *
     * @return array<int, array<string, mixed>>
     */
    private function monthlySeries(Event $event): array
    {
        $anchor = max(today(), $event->end_date);
        $start = $anchor->copy()->startOfMonth()->subMonths(5);

        $ledgerEntriesByMonth = $event->registrationFinancialEntries()
            ->where('occurred_at', '>=', $start)
            ->where('occurred_at', '<=', $anchor->copy()->endOfMonth())
            ->get(['type', 'amount', 'occurred_at'])
            ->groupBy(fn (EventRegistrationFinancialEntry $entry): string => $entry->occurred_at->format('Y-m'));

        $ledgerPaymentsByMonth = $ledgerEntriesByMonth
            ->map(fn ($rows): int => (int) $rows->where('type', 'payment')->sum('amount'));
        $ledgerRefundsByMonth = $ledgerEntriesByMonth
            ->map(fn ($rows): int => (int) $rows->where('type', 'refund')->sum('amount'));

        $legacyRegistrationByMonth = $event->registrations()
            ->where('payment_status', 'paid')
            ->whereNotNull('confirmed_paid_at')
            ->where('confirmed_paid_at', '>=', $start)
            ->whereDoesntHave('financialEntries')
            ->get(['confirmed_paid_at', 'fee_total'])
            ->groupBy(fn (EventRegistration $r): string => Carbon::parse($r->confirmed_paid_at)->format('Y-m'))
            ->map(fn ($rows): int => (int) $rows->sum('fee_total'));

        $legacyRefundByMonth = $event->registrations()
            ->whereDoesntHave('financialEntries')
            ->whereNotNull('refunded_at')
            ->where('refunded_at', '>=', $start)
            ->where('refunded_at', '<=', $anchor->copy()->endOfMonth())
            ->get(['refunded_at', 'refund_amount'])
            ->groupBy(fn (EventRegistration $r): string => Carbon::parse($r->refunded_at)->format('Y-m'))
            ->map(fn ($rows): int => (int) $rows->sum('refund_amount'));

        $manualByMonth = $event->financeTransactions()
            ->whereBetween('occurred_on', [$start->toDateString(), $anchor->copy()->endOfMonth()->toDateString()])
            ->get(['type', 'amount', 'occurred_on'])
            ->groupBy(fn (EventFinanceTransaction $t): string => $t->occurred_on->format('Y-m'));

        $series = [];

        for ($i = 0; $i < 6; $i++) {
            $month = $start->copy()->addMonths($i);
            $key = $month->format('Y-m');
            $rows = $manualByMonth->get($key, collect());

            $series[] = [
                'label' => $month->translatedFormat('M'),
                'full' => $month->translatedFormat('M Y'),
                'income' => (int) ($ledgerPaymentsByMonth->get($key) ?? 0)
                    + (int) ($legacyRegistrationByMonth->get($key) ?? 0)
                    + (int) $rows->where('type', 'income')->sum(fn ($t) => (float) $t->amount),
                'refund' => (int) ($ledgerRefundsByMonth->get($key) ?? 0)
                    + (int) ($legacyRefundByMonth->get($key) ?? 0),
                'expense' => (int) $rows->where('type', 'expense')->sum(fn ($t) => (float) $t->amount),
            ];
        }

        return $series;
    }

    private function authorizeManager(Request $request, Event $event): bool
    {
        $isAdmin = $request->user()->role === 'admin';
        $canManageAsOwner = ! $isAdmin
            && $event->submitted_by === $request->user()->id
            && $event->status === 'approved';

        abort_unless($isAdmin || $canManageAsOwner, 403);

        return $isAdmin;
    }
}
