<?php

use App\Http\Controllers\Admin\DataManagementController;
use App\Http\Controllers\Admin\EventOwnershipController;
use App\Http\Controllers\Admin\EventSubmissionController;
use App\Http\Controllers\Admin\ThemeSettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\EventCategoryController;
use App\Http\Controllers\EventFinanceController;
use App\Http\Controllers\EventListingController;
use App\Http\Controllers\EventRaceDayController;
use App\Http\Controllers\EventRaceFormatController;
use App\Http\Controllers\EventRaceOrderController;
use App\Http\Controllers\EventRegistrationController;
use App\Http\Controllers\EventRegistrationManagementController;
use App\Http\Controllers\GuestDashboardController;
use App\Http\Controllers\NikLookupController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicEventApplicationController;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('publik.beranda', [
        'events' => Event::query()
            ->where('status', 'approved')
            ->whereDate('end_date', '>=', today())
            ->orderBy('start_date')
            ->get(),
    ]);
})->name('home');

Route::get('/lomba', EventListingController::class)->name('events.index');

// Direktori klub dan atlet terbuka untuk umum, karena justru dipakai orang
// yang belum punya akun untuk mencari klub atau mendaftarkan klubnya sendiri.
Route::get('/direktori', [DirectoryController::class, 'index'])->name('directory.index');
Route::get('/direktori/daftar-klub', [DirectoryController::class, 'createClub'])->name('directory.clubs.create');
Route::post('/direktori/daftar-klub', [DirectoryController::class, 'storeClub'])
    ->middleware('throttle:6,1')
    ->name('directory.clubs.store');

Route::middleware('guest')->group(function () {
    Route::get('/daftar', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/daftar', [RegisteredUserController::class, 'store'])->middleware('throttle:6,1')->name('register.store');
    Route::get('/masuk', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/masuk', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
});

Route::post('/keluar', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::post('/nik/baca', NikLookupController::class)->middleware('throttle:30,1')->name('nik.lookup');
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
    Route::get('/profil', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profil', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'event-access'])->group(function () {
    Route::get('/dasbor-peserta', GuestDashboardController::class)->name('guest.dashboard');
    Route::get('/kejuaraan/{event}/atlet', [EventRegistrationManagementController::class, 'index'])
        ->name('events.registrations.index');
    Route::get('/kejuaraan/{event}/atlet/simpan', [EventRegistrationManagementController::class, 'export'])
        ->name('events.registrations.export');
    Route::patch('/kejuaraan/{event}/atlet/publikasi', [EventRegistrationManagementController::class, 'publish'])
        ->name('events.registrations.publish');
    Route::post('/kejuaraan/{event}/atlet/bagi-kategori', [EventRegistrationManagementController::class, 'assignCategories'])
        ->name('events.registrations.categories.assign');
    Route::post('/kejuaraan/{event}/atlet/{registration}/perubahan/keputusan', [EventRegistrationManagementController::class, 'reviewParticipantChange'])
        ->scopeBindings()
        ->name('events.registrations.changes.review');
    Route::post('/kejuaraan/{event}/atlet/{registration}/pembatalan/keputusan', [EventRegistrationManagementController::class, 'reviewCancellation'])
        ->scopeBindings()
        ->name('events.registrations.cancellations.review');
    Route::get('/dasbor-peserta/kejuaraan/{event}/kategori', [EventCategoryController::class, 'edit'])->name('owner.events.categories.edit');
    Route::put('/dasbor-peserta/kejuaraan/{event}/kategori', [EventCategoryController::class, 'update'])->name('owner.events.categories.update');
    Route::get('/dasbor-peserta/kejuaraan/{event}/keuangan', [EventFinanceController::class, 'index'])->name('owner.events.finance.index');
    Route::post('/dasbor-peserta/kejuaraan/{event}/keuangan', [EventFinanceController::class, 'store'])->name('owner.events.finance.store');
    // Sengaja tanpa ->scopeBindings(): implicit binding akan mencari relasi
    // "transactions()" pada Event, sedangkan relasinya bernama
    // financeTransactions(). Kecocokan event_id dicek di controller.
    Route::patch('/dasbor-peserta/kejuaraan/{event}/keuangan/{transaction}', [EventFinanceController::class, 'update'])
        ->name('owner.events.finance.update');
    Route::delete('/dasbor-peserta/kejuaraan/{event}/keuangan/{transaction}', [EventFinanceController::class, 'destroy'])
        ->name('owner.events.finance.destroy');
    // Kategori dikirim lewat query string (?kategori=) karena nama kategori
    // bebas berisi spasi dan simbol sehingga tidak aman dipakai sebagai segmen URL.
    Route::get('/dasbor-peserta/kejuaraan/{event}/perlombaan', [EventRaceFormatController::class, 'index'])
        ->name('owner.events.race.format');
    Route::put('/dasbor-peserta/kejuaraan/{event}/perlombaan', [EventRaceFormatController::class, 'update'])
        ->name('owner.events.race.format.update');
    // Rilis membuat susunan terlihat di halaman urutan lomba untuk semua
    // peserta dan klub yang masuk ke akunnya.
    Route::post('/dasbor-peserta/kejuaraan/{event}/perlombaan/rilis', [EventRaceFormatController::class, 'release'])
        ->name('owner.events.race.release');
    Route::delete('/dasbor-peserta/kejuaraan/{event}/perlombaan/rilis', [EventRaceFormatController::class, 'unrelease'])
        ->name('owner.events.race.unrelease');
    Route::get('/dasbor-peserta/kejuaraan/{event}/perlombaan/hari', [EventRaceDayController::class, 'show'])
        ->name('owner.events.race.day');
    Route::post('/dasbor-peserta/kejuaraan/{event}/perlombaan/babak', [EventRaceDayController::class, 'buildHeats'])
        ->name('owner.events.race.heats.build');
    Route::delete('/dasbor-peserta/kejuaraan/{event}/perlombaan/babak', [EventRaceDayController::class, 'resetHeats'])
        ->name('owner.events.race.heats.reset');
    Route::post('/dasbor-peserta/kejuaraan/{event}/perlombaan/grup/{group}/siapkan', [EventRaceDayController::class, 'prepareGroup'])
        ->name('owner.events.race.group.prepare');
    Route::post('/dasbor-peserta/kejuaraan/{event}/perlombaan/tunda/{entry}', [EventRaceDayController::class, 'postpone'])
        ->name('owner.events.race.entry.postpone');
    Route::patch('/dasbor-peserta/kejuaraan/{event}/perlombaan/hasil/{heat}', [EventRaceDayController::class, 'updateHeat'])
        ->name('owner.events.race.heat.update');
    Route::post('/dasbor-peserta/kejuaraan/{event}/perlombaan/tahap', [EventRaceDayController::class, 'advance'])
        ->name('owner.events.race.advance');
    Route::get('/kejuaraan/{event}/urutan-lomba', [EventRaceOrderController::class, 'show'])
        ->name('events.race.order');
    Route::put('/kejuaraan/{event}/atlet/{registration}', [EventRegistrationManagementController::class, 'update'])
        ->scopeBindings()
        ->name('events.registrations.update');
    Route::post('/kejuaraan/{event}/atlet/{registration}/konfirmasi-pembayaran', [EventRegistrationManagementController::class, 'confirmPayment'])
        ->scopeBindings()
        ->name('events.registrations.payment.confirm');
    Route::get('/kejuaraan/{event}/atlet/{registration}/bukti', [EventRegistrationManagementController::class, 'proof'])
        ->scopeBindings()
        ->name('events.registrations.proof');
    Route::get('/direktori/daftar-atlet', [DirectoryController::class, 'createAthlete'])->name('directory.athletes.create');
    Route::post('/direktori/daftar-atlet', [DirectoryController::class, 'storeAthlete'])
        ->middleware('throttle:6,1')
        ->name('directory.athletes.store');
    Route::get('/direktori/klub/{club}/ubah', [DirectoryController::class, 'editClub'])->name('directory.clubs.edit');
    Route::patch('/direktori/klub/{club}', [DirectoryController::class, 'updateClub'])->name('directory.clubs.update');
    Route::get('/ajukan-event', [PublicEventApplicationController::class, 'form'])->name('event-applications.create');
    Route::post('/ajukan-event', [PublicEventApplicationController::class, 'store'])
        ->middleware('throttle:4,1')
        ->name('event-applications.store');
    Route::get('/ajukan-event/{event}', [PublicEventApplicationController::class, 'form'])
        ->name('event-applications.edit');
    Route::put('/ajukan-event/{event}', [PublicEventApplicationController::class, 'update'])
        ->middleware('throttle:4,1')
        ->name('event-applications.update');
    Route::delete('/ajukan-event/{event}', [PublicEventApplicationController::class, 'destroy'])
        ->name('event-applications.destroy');
    Route::patch('/ajukan-event/{event}/pendaftaran', [PublicEventApplicationController::class, 'toggleRegistration'])
        ->name('event-applications.toggle-registration');
    Route::get('/lomba/{event}/daftar', [EventRegistrationController::class, 'create'])->name('events.registration.create');
    Route::post('/lomba/{event}/daftar', [EventRegistrationController::class, 'store'])
        ->middleware('throttle:8,1')
        ->name('events.registration.store');
    Route::put('/lomba/{event}/atlet/{registration}', [EventRegistrationController::class, 'updateOwn'])
        ->scopeBindings()
        ->name('events.registration.update-own');
    Route::delete('/lomba/{event}/atlet/{registration}', [EventRegistrationController::class, 'destroyOwn'])
        ->scopeBindings()
        ->name('events.registration.destroy-own');
    Route::post('/lomba/{event}/atlet/{registration}/bukti', [EventRegistrationController::class, 'uploadProof'])
        ->scopeBindings()
        ->middleware('throttle:8,1')
        ->name('events.registration.proof.store');
    Route::post('/lomba/{event}/atlet/{registration}/konfirmasi-whatsapp', [EventRegistrationController::class, 'confirmViaWhatsapp'])
        ->scopeBindings()
        ->middleware('throttle:8,1')
        ->name('events.registration.whatsapp');

    // API endpoint untuk resolusi kategori otomatis
    Route::post('/lomba/{event}/resolve-category', [EventRegistrationController::class, 'resolveCategory'])
        ->name('events.registration.resolve-category');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/', function () {
        $activeEvents = Event::query()
            ->where('status', 'approved')
            ->whereDate('end_date', '>=', today());
        $events = (clone $activeEvents)
            ->withCount('registrations')
            ->orderBy('start_date')
            ->get();
        $eventIds = (clone $activeEvents)->select('id')->pluck('id');

        return view('admin.dashboard', [
            'events' => $events,
            'activeEventCount' => (clone $activeEvents)->count(),
            'athleteCount' => EventRegistration::query()->whereIn('event_id', $eventIds)->count(),
            'clubCount' => EventRegistration::query()->whereIn('event_id', $eventIds)->whereNotNull('club_name')->distinct()->count('club_name'),
            'pendingApplicationCount' => Event::query()->where('status', 'pending')->whereNotNull('submitted_by')->count(),
        ]);
    })->name('dashboard');
    Route::patch('/kejuaraan/{event}/pemilik', [EventOwnershipController::class, 'update'])
        ->name('events.ownership.update');
    Route::get('/pengajuan-event', [EventSubmissionController::class, 'index'])->name('event-submissions.index');
    Route::patch('/pengajuan-event/{event}/setujui', [EventSubmissionController::class, 'approve'])->name('event-submissions.approve');
    Route::patch('/pengajuan-event/{event}/tolak', [EventSubmissionController::class, 'reject'])->name('event-submissions.reject');
    Route::get('/akun', [UserController::class, 'index'])->name('accounts.index');
    Route::get('/akun/buat', [UserController::class, 'form'])->name('accounts.create');
    Route::post('/akun', [UserController::class, 'store'])->name('accounts.store');
    Route::get('/akun/{user}/edit', [UserController::class, 'form'])->name('accounts.edit');
    Route::put('/akun/{user}', [UserController::class, 'update'])->name('accounts.update');
    Route::post('/akun/{user}/kirim-verifikasi', [UserController::class, 'resendVerification'])
        ->middleware('throttle:6,1')
        ->name('accounts.verification.resend');
    Route::post('/akun/{user}/verifikasi-manual', [UserController::class, 'verifyEmail'])
        ->name('accounts.verification.verify');
    Route::get('/pengaturan/tema', [ThemeSettingController::class, 'edit'])->name('settings.theme.edit');
    Route::patch('/pengaturan/tema', [ThemeSettingController::class, 'update'])->name('settings.theme.update');
    Route::get('/data/backup', [DataManagementController::class, 'backup'])->name('data.backup');
    Route::post('/data/backup', [DataManagementController::class, 'downloadBackup'])->name('data.backup.download');
    Route::post('/data/restore', [DataManagementController::class, 'restore'])->name('data.restore');
    Route::get('/data/hapus', [DataManagementController::class, 'delete'])->name('data.delete');
    Route::post('/data/hapus/konfirmasi', [DataManagementController::class, 'reviewDeletion'])->name('data.delete.review');
    Route::post('/data/hapus', [DataManagementController::class, 'destroySelected'])->name('data.delete.destroy');
});
