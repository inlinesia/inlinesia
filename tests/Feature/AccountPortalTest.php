<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AccountPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_open_registration_and_login_pages(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Pengelola klub')
            ->assertSee('Individu')
            ->assertSee('Pemerintah')
            ->assertDontSee('value="admin"', false);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Gunakan email dan kata sandi akun Anda.');
    }

    public function test_public_registration_creates_and_signs_in_a_non_admin_account(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Anggota Klub',
            'email' => 'anggota@example.test',
            'phone' => '081234567890',
            'organization' => 'Klub Roda',
            'role' => 'club_manager',
            'password' => 'SandiAman123!',
            'password_confirmation' => 'SandiAman123!',
        ]);

        $user = User::query()->where('email', 'anggota@example.test')->firstOrFail();

        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame('club_manager', $user->role);
        $this->assertFalse($user->is_primary_admin);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue(Hash::check('SandiAman123!', $user->password));
    }

    public function test_registration_sends_a_verification_email_and_the_signed_link_verifies_the_account(): void
    {
        Notification::fake();

        $this->post(route('register.store'), [
            'name' => 'Calon Peserta',
            'email' => 'calon@example.test',
            'role' => 'individual',
            'password' => 'SandiAman123!',
            'password_confirmation' => 'SandiAman123!',
        ])->assertRedirect(route('verification.notice'));

        $user = User::query()->where('email', 'calon@example.test')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->assertSame('inlinesia@gmail.com', config('mail.from.address'));

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(30),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
        );

        $this->get($url)
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('status', 'Email berhasil diverifikasi. Anda sekarang dapat mengajukan atau mendaftar event.');

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_public_registration_cannot_select_admin_role(): void
    {
        $this->from(route('register'))
            ->post(route('register.store'), [
                'name' => 'Pendaftar',
                'email' => 'admin-palsu@example.test',
                'role' => 'admin',
                'password' => 'SandiAman123!',
                'password_confirmation' => 'SandiAman123!',
            ])
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('role');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_users_can_login_update_their_profile_and_logout(): void
    {
        $user = User::factory()->create([
            'email' => 'profil@example.test',
            'password' => Hash::make('SandiAman123!'),
            'role' => 'government',
            'organization' => 'Dinas Pemuda dan Olahraga',
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'SandiAman123!',
        ])->assertRedirect(route('profile.show'));

        $this->assertAuthenticatedAs($user);

        $this->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Profil Anda')
            ->assertSee('Dinas Pemuda dan Olahraga')
            ->assertSee('Jenis akun')
            ->assertSee('Beranda utama')
            ->assertSee(route('home'), false)
            ->assertSee('Daftar kejuaraan')
            ->assertSee('Fitur platform')
            ->assertSee('Alur penggunaan')
            ->assertSee('Tentang kami')
            ->assertSee('Buka dasbor')
            ->assertSee(route('guest.dashboard').'#pendaftaran-saya', false)
            ->assertSee(route('event-applications.create'), false)
            ->assertSee('Daftarkan event');

        $this->put(route('profile.update'), [
            'name' => 'Perwakilan Pemerintah',
            'phone' => '081234567890',
            'organization' => 'Dinas Pemuda dan Olahraga',
            'address' => 'Kediri',
        ])->assertRedirect(route('profile.show'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Perwakilan Pemerintah',
            'role' => 'government',
            'organization' => 'Dinas Pemuda dan Olahraga',
        ]);

        $this->assertTrue(Hash::check('SandiAman123!', $user->fresh()->password));

        $this->put(route('profile.update'), [
            'name' => 'Perwakilan Pemerintah',
            'phone' => '081234567890',
            'organization' => 'Dinas Pemuda dan Olahraga',
            'address' => 'Kediri',
            'current_password' => 'SandiAman123!',
            'password' => 'PasswordBaru456!',
            'password_confirmation' => 'PasswordBaru456!',
        ])->assertRedirect(route('profile.show'));

        $this->assertTrue(Hash::check('PasswordBaru456!', $user->fresh()->password));

        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_primary_admin_email_is_fixed_in_profiles_and_when_saving_the_account(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin Inlinesia',
            'email' => 'admin-owner@example.test',
            'role' => 'admin',
        ]);
        $admin->forceFill(['is_primary_admin' => true])->save();

        $this->assertSame('inlinesia@gmail.com', $admin->fresh()->email);

        $this->actingAs($admin)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Email admin utama Inlinesia bersifat tetap.')
            ->assertSee('Beranda utama')
            ->assertSee('Buka panel admin')
            ->assertSee(route('admin.dashboard'), false)
            ->assertSee('class="profile-panel-link" href="'.route('guest.dashboard').'"', false)
            ->assertSee('Kelola event')
            ->assertSee('Buka dasbor');

        // Header publik sengaja tidak memuat tautan panel admin; panel admin
        // hanya showcased lewat halaman profil.
        $this->get(route('home'))
            ->assertSee(route('guest.dashboard'), false)
            ->assertDontSee(route('admin.dashboard'), false)
            ->assertSee('Beranda utama')
            ->assertSee('Daftar kejuaraan')
            ->assertSee('Fitur platform')
            ->assertSee('Alur penggunaan')
            ->assertSee('Tentang kami')
            ->assertSee('Buka dasbor')
            ->assertSee(route('guest.dashboard').'#pendaftaran-saya', false)
            ->assertSee(route('event-applications.create'), false)
            ->assertSee('Pendaftaran saya');

        $this->get(route('guest.dashboard'))
            ->assertOk()
            ->assertSee('PENGELOLAAN EVENT ADMIN')
            ->assertSee('Ajukan event');

        $this->actingAs($admin)
            ->put(route('profile.update'), [
                'name' => 'Admin Inlinesia',
                'email' => 'alamat-lain@example.test',
            ])
            ->assertRedirect(route('profile.show'));

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'email' => 'inlinesia@gmail.com',
            'role' => 'admin',
            'is_primary_admin' => true,
        ]);
    }

    public function test_admin_can_edit_an_account_and_email_changes_require_reverification(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $account = User::factory()->create([
            'name' => 'Anggota Lama',
            'email' => 'anggota-lama@example.test',
            'role' => 'individual',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.accounts.index'))
            ->assertOk()
            ->assertSee('Tambah akun')
            // Sunting lewat dialog, jadi baris membawa data akun sebagai
            // atribut data-* dan bukan tautan halaman.
            ->assertSee('data-account-modal-open', false)
            ->assertSee('data-account-id="'.$account->id.'"', false)
            ->assertDontSee(route('admin.accounts.edit', $account), false);

        $this->get(route('admin.accounts.edit', $account))
            ->assertOk()
            ->assertSee('Edit akun')
            ->assertSee('anggota-lama@example.test');

        $this->put(route('admin.accounts.update', $account), [
            'name' => 'Anggota Baru',
            'email' => 'anggota-baru@example.test',
            'phone' => '081234567890',
            'organization' => 'Klub Roda',
            'address' => 'Kediri',
            'role' => 'club_manager',
            'password' => 'SandiBaru123!',
            'password_confirmation' => 'SandiBaru123!',
        ])->assertRedirect(route('admin.accounts.index'))
            ->assertSessionHas('status', 'Data akun berhasil diperbarui. Tautan verifikasi email dikirim ke anggota-baru@example.test.');

        $account->refresh();
        $this->assertSame('Anggota Baru', $account->name);
        $this->assertSame('anggota-baru@example.test', $account->email);
        $this->assertSame('club_manager', $account->role);
        $this->assertSame('Klub Roda', $account->organization);
        $this->assertSame('Kediri', $account->address);
        $this->assertNull($account->email_verified_at);
        $this->assertTrue(Hash::check('SandiBaru123!', $account->password));
        Notification::assertSentTo($account, VerifyEmail::class);
    }

    public function test_admin_can_search_filter_and_sort_the_account_list(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.test',
            'phone' => '0811111111',
            'organization' => 'Klub Roda Kediri',
            'role' => 'individual',
        ]);
        User::factory()->unverified()->create([
            'name' => 'Citra Dewi',
            'email' => 'citra@example.test',
            'phone' => '0822222222',
            'role' => 'club_manager',
        ]);

        // Pencarian menempuh nama, email, nomor WhatsApp, dan instansi.
        $this->actingAs($admin)
            ->get(route('admin.accounts.index', ['q' => 'Budi']))
            ->assertOk()
            ->assertSee('budi@example.test')
            ->assertDontSee('citra@example.test');

        $this->get(route('admin.accounts.index', ['q' => '082222']))
            ->assertOk()
            ->assertSee('citra@example.test')
            ->assertDontSee('budi@example.test');

        $this->get(route('admin.accounts.index', ['q' => 'Klub Roda']))
            ->assertOk()
            ->assertSee('budi@example.test')
            ->assertDontSee('citra@example.test');

        // Filter peran dan status verifikasi.
        $this->get(route('admin.accounts.index', ['role' => 'club_manager']))
            ->assertOk()
            ->assertSee('citra@example.test')
            ->assertDontSee('budi@example.test');

        $this->get(route('admin.accounts.index', ['status' => 'unverified']))
            ->assertOk()
            ->assertSee('citra@example.test')
            ->assertDontSee('budi@example.test');

        // Sortir nama menaik menaruh Budi sebelum Citra.
        $this->get(route('admin.accounts.index', ['sort' => 'nama', 'direction' => 'asc']))
            ->assertOk()
            ->assertSeeInOrder(['Budi Santoso', 'Citra Dewi']);

        // Pencarian tanpa hasil menampilkan pesan kosong, bukan tabel kosong.
        $this->get(route('admin.accounts.index', ['q' => 'tidak-ada']))
            ->assertOk()
            ->assertSee('Tidak ada akun yang cocok');
    }

    public function test_the_account_list_shows_whatsapp_as_a_clickable_link(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create([
            'name' => 'Pemilik Nomor',
            'email' => 'nomor@example.test',
            'phone' => '+62 812-3456-7890',
        ]);
        User::factory()->create([
            'name' => 'Tanpa Nomor',
            'email' => 'tanpa-nomor@example.test',
            'phone' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.accounts.index'))
            ->assertOk()
            ->assertSee('<th>WhatsApp</th>', false)
            ->assertSee('https://wa.me/6281234567890', false)
            ->assertSee('+62 812-3456-7890');
    }

    public function test_admin_can_manually_verify_an_email_and_resend_its_verification_link(): void
    {
        Notification::fake();
        EventFacade::fake([Verified::class]);

        $admin = User::factory()->create(['role' => 'admin']);
        $account = User::factory()->unverified()->create();

        $this->actingAs($admin)
            ->get(route('admin.accounts.index'))
            ->assertOk()
            ->assertSee('Belum verifikasi')
            ->assertSee('Kirim email')
            ->assertSee('Verifikasi manual');

        $this->post(route('admin.accounts.verification.resend', $account))
            ->assertRedirect(route('admin.accounts.index'))
            ->assertSessionHas('status', "Tautan verifikasi dikirim ke {$account->email}.");
        Notification::assertSentTo($account, VerifyEmail::class);

        $this->post(route('admin.accounts.verification.verify', $account))
            ->assertRedirect(route('admin.accounts.index'))
            ->assertSessionHas('status', "Email {$account->email} ditandai terverifikasi oleh Admin.");

        $this->assertNotNull($account->fresh()->email_verified_at);
        EventFacade::assertDispatched(Verified::class);
        $this->get(route('admin.accounts.index'))
            ->assertOk()
            ->assertSee('Terverifikasi');
    }

    public function test_non_admin_cannot_manually_verify_another_users_email(): void
    {
        $account = User::factory()->unverified()->create();

        $this->actingAs(User::factory()->create(['role' => 'individual']))
            ->post(route('admin.accounts.verification.verify', $account))
            ->assertForbidden();

        $this->assertNull($account->fresh()->email_verified_at);
    }

    public function test_primary_admin_cannot_change_its_email_or_role_from_admin_edit(): void
    {
        $primaryAdmin = User::factory()->create([
            'email' => 'owner-before@example.test',
            'role' => 'admin',
        ]);
        $primaryAdmin->forceFill(['is_primary_admin' => true])->save();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.accounts.edit', $primaryAdmin))
            ->assertOk()
            ->assertSee('inlinesia@gmail.com')
            ->assertSee('Email admin utama Inlinesia dikunci');

        $this->put(route('admin.accounts.update', $primaryAdmin), [
            'name' => 'Admin Utama',
            'email' => 'email-lain@example.test',
            'role' => 'individual',
        ])->assertRedirect(route('admin.accounts.index'));

        $this->assertDatabaseHas('users', [
            'id' => $primaryAdmin->id,
            'email' => 'inlinesia@gmail.com',
            'role' => 'admin',
            'is_primary_admin' => true,
        ]);
    }

    public function test_only_admins_can_open_admin_and_account_management_pages(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->get(route('admin.accounts.index'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create(['role' => 'individual']))
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_admin_can_access_pages_restricted_to_other_roles(): void
    {
        Route::middleware('role:government')->get('/test/government-only', fn () => response('allowed'));

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/test/government-only')
            ->assertOk()
            ->assertSeeText('allowed');

        $this->actingAs(User::factory()->create(['role' => 'individual']))
            ->get('/test/government-only')
            ->assertForbidden();
    }

    public function test_an_admin_can_create_accounts_for_all_supported_roles(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.accounts.index'))
            ->assertOk()
            ->assertSee('Kelola akun')
            ->assertSee('akun terdaftar')
            ->assertSee('Admin');

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.accounts.create'))
            ->assertOk()
            ->assertSee('Admin')
            ->assertSee('Pemerintah');

        $this->post(route('admin.accounts.store'), [
            'name' => 'Admin Tambahan',
            'email' => 'admin-tambahan@example.test',
            'role' => 'admin',
            'password' => 'SandiAman123!',
            'password_confirmation' => 'SandiAman123!',
        ])->assertRedirect(route('admin.accounts.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'admin-tambahan@example.test',
            'role' => 'admin',
            'is_primary_admin' => false,
        ]);
    }
}
