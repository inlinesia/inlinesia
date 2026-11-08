<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventFinanceTransaction;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman keuangan event.
 *
 * Dua sifat yang dikunci di sini:
 *
 * 1. Pemasukan pendaftaran SELALU dihitung dari tabel registrations
 *    (pembayaran berstatus "paid"), tidak pernah dari catatan manual. Kalau
 *    tidak begitu, nominal di halaman bisa berbeda dari yang dilihat pengelola
 *    di "Kelola peserta & pembayaran".
 * 2. Akses mengikuti halaman "Atur kategori": pemilik event yang sudah
 *    disetujui, atau Admin. Pengguna lain harus 403.
 */
class EventFinanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_of_an_approved_event_can_open_the_finance_page(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->actingAs($owner)
            ->get(route('owner.events.finance.index', $event))
            ->assertOk()
            ->assertViewIs('verified.events.finance.index')
            ->assertSee('Keuangan event')
            ->assertSee('Pemasukan pendaftaran');
    }

    public function test_admin_can_open_the_finance_page_for_any_event(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->actingAs($admin)
            ->get(route('owner.events.finance.index', $event))
            ->assertOk()
            ->assertSee('PENGELOLAAN ADMIN');
    }

    public function test_user_without_the_event_is_forbidden(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $stranger = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->actingAs($stranger)
            ->get(route('owner.events.finance.index', $event))
            ->assertForbidden();

        $this->actingAs($stranger)
            ->post(route('owner.events.finance.store', $event), $this->validTransaction())
            ->assertForbidden();
    }

    public function test_owner_cannot_finance_an_event_that_is_not_approved_yet(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);
        $event->update(['status' => 'pending']);

        $this->actingAs($owner)
            ->get(route('owner.events.finance.index', $event))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->get(route('owner.events.finance.index', $event))
            ->assertRedirect(route('login'));
    }

    public function test_registration_income_counts_only_confirmed_payments(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->makeRegistration($event, feeTotal: 150000, paymentStatus: 'paid');
        $this->makeRegistration($event, feeTotal: 250000, paymentStatus: 'paid');
        $this->makeRegistration($event, feeTotal: 999999, paymentStatus: 'pending');

        $this->actingAs($owner)
            ->get(route('owner.events.finance.index', $event))
            ->assertOk()
            ->assertSee('Rp 400.000')
            ->assertDontSee('Rp 1.649.999');
    }

    public function test_registration_income_ignores_manual_notes(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->makeRegistration($event, feeTotal: 150000, paymentStatus: 'paid');

        EventFinanceTransaction::create([
            'event_id' => $event->id,
            'type' => 'income',
            'category' => 'registration',
            'description' => 'Pemasukan pendaftaran versi manual',
            'amount' => 700000,
            'occurred_on' => today()->toDateString(),
            'created_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->get(route('owner.events.finance.index', $event))
            ->assertOk()
            ->assertSee('Rp 150.000');
    }

    public function test_owner_can_store_an_expense(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->actingAs($owner)
            ->post(route('owner.events.finance.store', $event), [
                'type' => 'expense',
                'category' => 'venue',
                'description' => 'Sewa lapangan Gajahan',
                'amount' => 6000000,
                'occurred_on' => today()->subDay()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('event_finance_transactions', [
            'event_id' => $event->id,
            'type' => 'expense',
            'category' => 'venue',
            'amount' => 6000000,
            'created_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->get(route('owner.events.finance.index', $event))
            ->assertSee('Sewa lapangan Gajahan')
            ->assertSee('Sewa lokasi');
    }

    public function test_expense_must_use_an_expense_category(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        // "sponsor" hanya valid untuk income.
        $this->actingAs($owner)
            ->post(route('owner.events.finance.store', $event), [
                'type' => 'expense',
                'category' => 'sponsor',
                'description' => 'Salah kategori',
                'amount' => 1000,
                'occurred_on' => today()->toDateString(),
            ])
            ->assertSessionHasErrors('category');

        $this->assertDatabaseMissing('event_finance_transactions', ['description' => 'Salah kategori']);
    }

    public function test_amount_must_be_a_positive_number(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->actingAs($owner)
            ->post(route('owner.events.finance.store', $event), [
                'type' => 'expense',
                'category' => 'venue',
                'description' => 'Nol rupiah',
                'amount' => 0,
                'occurred_on' => today()->toDateString(),
            ])
            ->assertSessionHasErrors('amount');
    }

    public function test_transaction_date_cannot_be_in_the_future(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->actingAs($owner)
            ->post(route('owner.events.finance.store', $event), [
                'type' => 'income',
                'category' => 'sponsor',
                'description' => 'Sponsor masa depan',
                'amount' => 5000000,
                'occurred_on' => today()->addMonth()->toDateString(),
            ])
            ->assertSessionHasErrors('occurred_on');
    }

    public function test_owner_can_update_and_delete_a_transaction(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $transaction = EventFinanceTransaction::create([
            'event_id' => $event->id,
            'type' => 'expense',
            'category' => 'marketing',
            'description' => 'Poster lama',
            'amount' => 1200000,
            'occurred_on' => today()->subDays(3)->toDateString(),
            'created_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->patch(route('owner.events.finance.update', [$event, $transaction]), [
                'type' => 'income',
                'category' => 'sponsor',
                'description' => 'Sponsor baru',
                'amount' => 3500000,
                'occurred_on' => today()->subDay()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('event_finance_transactions', [
            'id' => $transaction->id,
            'type' => 'income',
            'category' => 'sponsor',
            'description' => 'Sponsor baru',
        ]);

        $this->actingAs($owner)
            ->delete(route('owner.events.finance.destroy', [$event, $transaction]))
            ->assertRedirect();

        $this->assertDatabaseMissing('event_finance_transactions', ['id' => $transaction->id]);
    }

    public function test_transaction_from_another_event_cannot_be_touched(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);
        $other = $this->createEvent($owner, 'Event lain');

        $transaction = EventFinanceTransaction::create([
            'event_id' => $other->id,
            'type' => 'expense',
            'category' => 'venue',
            'description' => 'Sewa lapangan lain',
            'amount' => 500000,
            'occurred_on' => today()->toDateString(),
            'created_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->patch(route('owner.events.finance.update', [$event, $transaction]), [
                'type' => 'expense',
                'category' => 'venue',
                'description' => 'Dibajak',
                'amount' => 1,
                'occurred_on' => today()->toDateString(),
            ])
            ->assertNotFound();

        $this->actingAs($owner)
            ->delete(route('owner.events.finance.destroy', [$event, $transaction]))
            ->assertNotFound();

        $this->assertDatabaseHas('event_finance_transactions', [
            'id' => $transaction->id,
            'description' => 'Sewa lapangan lain',
        ]);
    }

    public function test_balance_combines_registration_income_sponsor_and_expenses(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->makeRegistration($event, feeTotal: 400000, paymentStatus: 'paid');

        foreach ([
            ['income', 'sponsor', 'Sponsor utama', 15000000],
            ['expense', 'venue', 'Sewa lokasi', 6000000],
            ['expense', 'prize', 'Piala', 1000000],
        ] as [$type, $category, $description, $amount]) {
            EventFinanceTransaction::create([
                'event_id' => $event->id,
                'type' => $type,
                'category' => $category,
                'description' => $description,
                'amount' => $amount,
                'occurred_on' => today()->toDateString(),
                'created_by' => $owner->id,
            ]);
        }

        // 400.000 + 15.000.000 - 7.000.000 = 8.400.000
        $this->actingAs($owner)
            ->get(route('owner.events.finance.index', $event))
            ->assertOk()
            ->assertSee('Rp 15.400.000')   // total pemasukan
            ->assertSee('Rp 7.000.000')    // total pengeluaran
            ->assertSee('Rp 8.400.000')    // saldo
            ->assertSee('Surplus');
    }

    public function test_page_explains_when_fees_have_not_been_calculated(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->makeRegistration($event, feeTotal: 0, paymentStatus: 'paid');

        $this->actingAs($owner)
            ->get(route('owner.events.finance.index', $event))
            ->assertOk()
            ->assertSee('Nominal tarif belum dihitung');
    }

    public function test_dashboard_links_to_the_finance_page_only_for_manageable_events(): void
    {
        $owner = User::factory()->create(['role' => 'organizer']);
        $event = $this->createEvent($owner);

        $this->actingAs($owner)
            ->get(route('guest.dashboard'))
            ->assertOk()
            ->assertSee(route('owner.events.finance.index', $event), false);
    }

    private function createEvent(User $owner, string $name = 'Kediri Roller Cup'): Event
    {
        $startDate = today()->addMonth();

        return Event::create([
            'submitted_by' => $owner->id,
            'name' => $name,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $startDate->copy()->addDay()->format('Y-m-d'),
            'venue' => 'GOR Padepokan PSHT',
            'venue_address' => 'Kediri',
            'organizer_whatsapp' => '081222333444',
            'scale' => 'Kota',
            'categories' => ['Junior'],
            'category_rules' => [],
            'races' => ['Classic Slalom'],
            'schedule' => ['Final'],
            'status' => 'approved',
        ]);
    }

    private function makeRegistration(Event $event, int $feeTotal, string $paymentStatus): EventRegistration
    {
        return EventRegistration::create([
            'event_id' => $event->id,
            'user_id' => User::factory()->create()->id,
            'participant_name' => 'Atlet Contoh',
            'birth_year' => 2012,
            'gender' => 'male',
            'category' => 'Junior',
            'races' => ['Classic Slalom'],
            'fee_total' => $feeTotal,
            'payment_status' => $paymentStatus,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validTransaction(): array
    {
        return [
            'type' => 'income',
            'category' => 'sponsor',
            'description' => 'Sponsor uji',
            'amount' => 1000,
            'occurred_on' => today()->toDateString(),
        ];
    }
}
