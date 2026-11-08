<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Daftar akun dengan pencarian, filter, dan sortir.
     *
     * Dibatasi 50 baris per halaman supaya satu halaman muat di kertas A4
     * saat dicetak, tanpa memotong satu entri di tengah.
     */
    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'role' => (string) $request->query('role', ''),
            'status' => (string) $request->query('status', ''),
        ];

        $sortOptions = [
            'terbaru' => 'Paling baru',
            'nama' => 'Nama A–Z',
            'email' => 'Email A–Z',
            'peran' => 'Jenis akun',
        ];
        $statusOptions = [
            'verified' => 'Sudah verifikasi',
            'unverified' => 'Belum verifikasi',
        ];

        $requestedSort = (string) $request->query('sort', '');
        $sortKey = array_key_exists($requestedSort, $sortOptions) ? $requestedSort : 'terbaru';
        $sort = [
            'key' => $sortKey,
            'direction' => $request->query('direction') === 'asc' ? 'asc' : 'desc',
        ];

        // Halaman ini dicetak sebagai daftar, jadi 50 baris per halaman sudah
        // muat di kertas A4 tanpa memotong entri di tengah.
        $users = User::query()
            ->when($filters['q'] !== '', fn ($query) => $query->where(function ($nested) use ($filters): void {
                $needle = '%'.$filters['q'].'%';
                $nested->where('name', 'like', $needle)
                    ->orWhere('email', 'like', $needle)
                    ->orWhere('phone', 'like', $needle)
                    ->orWhere('organization', 'like', $needle);
            }))
            ->when($filters['role'] !== '', fn ($query) => $query->where('role', $filters['role']))
            ->when($filters['status'] === 'verified', fn ($query) => $query->whereNotNull('email_verified_at'))
            ->when($filters['status'] === 'unverified', fn ($query) => $query->whereNull('email_verified_at'))
            ->orderBy(match ($sortKey) {
                'nama' => 'name',
                'email' => 'email',
                'peran' => 'role',
                default => 'id',
            }, $sort['direction'])
            ->paginate(50)
            ->withQueryString();

        return view('admin.accounts.index', [
            'users' => $users,
            'roles' => config('user-roles'),
            'filters' => $filters,
            'sort' => $sort,
            'sortOptions' => $sortOptions,
            'statusOptions' => $statusOptions,
        ]);
    }

    /**
     * Satu halaman untuk membuat akun baru dan menyunting akun yang sudah ada.
     * Tanpa $user, form menampilkan mode pembuatan; dengan $user, mode penyuntingan.
     */
    public function form(?User $user = null): View
    {
        return view('admin.accounts.form', [
            'account' => $user,
            'roles' => config('user-roles'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'organization' => ['nullable', 'string', 'max:180'],
            'address' => ['nullable', 'string', 'max:500'],
            'role' => ['required', 'in:admin,club_manager,individual,government'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        User::create([
            ...$validated,
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->route('admin.accounts.index')
            ->with('status', 'Akun baru berhasil dibuat.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $isPrimaryAdmin = $user->is_primary_admin;
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'organization' => ['nullable', 'string', 'max:180'],
            'address' => ['nullable', 'string', 'max:500'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ];

        if (! $isPrimaryAdmin) {
            $rules['email'] = ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)];
            $rules['role'] = ['required', Rule::in(array_keys(config('user-roles')))];
        }

        $validated = $request->validate($rules);
        $previousEmail = $user->email;
        $password = $validated['password'] ?? null;
        unset($validated['password']);

        if ($isPrimaryAdmin) {
            $validated['email'] = config('primary-admin.email');
            $validated['role'] = 'admin';
        }

        $user->fill($validated);
        if ($password !== null && $password !== '') {
            $user->password = Hash::make($password);
        }
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        $user->save();

        if ($user->wasChanged('email')) {
            $user->sendEmailVerificationNotification();
        }

        return redirect()->route('admin.accounts.index')
            ->with('status', $user->wasChanged('email') && $previousEmail !== $user->email
                ? "Data akun berhasil diperbarui. Tautan verifikasi email dikirim ke {$user->email}."
                : 'Data akun berhasil diperbarui.');
    }

    public function resendVerification(User $user): RedirectResponse
    {
        if ($user->hasVerifiedEmail()) {
            return redirect()->route('admin.accounts.index')
                ->with('status', "Email {$user->email} sudah terverifikasi.");
        }

        $user->sendEmailVerificationNotification();

        return redirect()->route('admin.accounts.index')
            ->with('status', "Tautan verifikasi dikirim ke {$user->email}.");
    }

    public function verifyEmail(User $user): RedirectResponse
    {
        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->route('admin.accounts.index')
            ->with('status', "Email {$user->email} ditandai terverifikasi oleh Admin.");
    }
}
