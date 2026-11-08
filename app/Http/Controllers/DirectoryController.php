<?php

namespace App\Http\Controllers;

use App\Models\Athlete;
use App\Models\Club;
use App\Models\User;
use App\Services\RegistrationIdentity;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Direktori klub dan atlet.
 *
 * Daftar klub terbuka untuk siapa saja, karena memang dipakai untuk mencari
 * klub yang mau diikuti. Daftar peserta berbeda: nama, tahun lahir, dan NIK
 * adalah data pribadi, jadi hanya pemilik klubnya, pemilik akunnya, dan admin
 * Inlinesia yang boleh melihatnya. NIK tetap disamarkan bahkan untuk mereka
 * yang boleh melihat daftarnya, kecuali pemiliknya sendiri.
 */
class DirectoryController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        $user = $request->user();
        $canSeeAthletes = $this->canSeeAthletes($user);

        $clubs = Club::query()
            ->withCount('athletes')
            ->when($term !== '', fn ($query) => $query->where('name', 'like', '%'.$term.'%'))
            ->orderBy('name')
            ->limit(100)
            ->get();

        $athletes = $canSeeAthletes
            ? Athlete::query()
                ->with('club')
                ->when($term !== '', fn ($query) => $query->search($term))
                // Admin melihat seluruh direktori; yang lain hanya atlet miliknya
                // sendiri atau atlet yang terdaftar pada klub yang dikelolanya.
                ->when(! $user?->isAdmin(), fn ($query) => $query->where(function ($inner) use ($user): void {
                    $inner->where('owner_user_id', $user?->id)
                        ->orWhereHas('club', fn ($club) => $club->where('owner_user_id', $user?->id));
                }))
                ->orderBy('name')
                ->limit(100)
                ->get()
            : collect();

        return view('publik.direktori.index', [
            'clubs' => $clubs,
            'athletes' => $athletes,
            'term' => $term,
            'viewer' => $user,
            'canSeeAthletes' => $canSeeAthletes,
        ]);
    }

    /**
     * Daftar peserta dibuka untuk admin, pemilik klub, dan pemilik akun atlet.
     *
     * Tamu serta akun yang tidak memiliki klub maupun atlet tidak melihat
     * daftarnya sama sekali, bukan sekadar disembunyikan lewat CSS.
     */
    private function canSeeAthletes(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return Club::query()->where('owner_user_id', $user->id)->exists()
            || Athlete::query()->where('owner_user_id', $user->id)->exists();
    }

    public function createClub(Request $request): View
    {
        return view('publik.direktori.klub', ['viewer' => $request->user()]);
    }

    public function storeClub(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->clubValidationRules(requireContact: true));

        // Klub yang sama tidak boleh didaftarkan dua kali. Perbandingan dibuat
        // longgar pada huruf besar/kecil, spasi di pinggir, dan spasi ganda
        // supaya "Skate House" dan "skate  house" dianggap klub yang sama.
        $duplicate = Club::query()
            ->whereRaw("LOWER(TRIM(REPLACE(name, '  ', ' '))) = ?", [$this->normalizeName($validated['name'])])
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'name' => 'Klub dengan nama tersebut sudah ada di direktori.',
            ]);
        }

        Club::query()->create([
            ...$validated,
            'name' => $this->normalizeName($validated['name']),
            'owner_user_id' => $request->user()?->id,
        ]);

        return redirect()->route('directory.index')
            ->with('status', 'Klub berhasil didaftarkan dan sudah bisa ditemukan di direktori.');
    }

    /**
     * Mengubah data klub yang dikuasai pemiliknya sendiri.
     *
     * Kontak hanya boleh diisi oleh pemiliknya, karena halaman ini dipublikasikan
     * untuk umum dan nomor yang tertulis di sini akan dilihat siapa pun.
     */
    public function editClub(Request $request, Club $club): View
    {
        abort_unless($club->isManagedBy($request->user()), 403, 'Hanya pemilik klub yang boleh mengubah data ini.');

        return view('publik.direktori.klub-edit', [
            'club' => $club,
            'viewer' => $request->user(),
        ]);
    }

    public function updateClub(Request $request, Club $club): RedirectResponse
    {
        abort_unless($club->isManagedBy($request->user()), 403, 'Hanya pemilik klub yang boleh mengubah data ini.');

        $validated = $request->validate($this->clubValidationRules(requireContact: false));

        $club->update([
            'name' => $this->normalizeName($validated['name']),
            'city' => $validated['city'] ?? null,
            'contact_name' => $validated['contact_name'] ?? null,
            'contact_phone' => $validated['contact_phone'] ?? null,
            'contact_email' => $validated['contact_email'] ?? null,
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->route('directory.index')
            ->with('status', "Data klub {$club->name} berhasil diperbarui.");
    }

    /**
     * Aturan isian klub.
     *
     * Saat pertama mendaftar, kontak wajib karena tujuannya memang agar klub
     * bisa dihubungi. Saat mengubah, kontak dibuat opsional karena pemilik
     * boleh memilih tidak mempublikasikan nomornya sama sekali.
     *
     * @return array<string, array<int, mixed>>
     */
    private function clubValidationRules(bool $requireContact): array
    {
        $contact = $requireContact ? 'required' : 'nullable';

        return [
            'name' => ['required', 'string', 'max:180'],
            'city' => ['nullable', 'string', 'max:120'],
            'contact_name' => [$contact, 'string', 'max:120'],
            'contact_phone' => [$contact, 'string', 'max:40'],
            'contact_email' => ['nullable', 'email', 'max:180'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Nama klub dibersihkan dari spasi berlebih supaya mudah dicari dan tidak
     * menghasilkan dua entri yang sebenarnya sama.
     */
    private function normalizeName(string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }

    public function createAthlete(Request $request): View
    {
        return view('publik.direktori.atlet', [
            'viewer' => $request->user(),
            'clubs' => Club::query()->orderBy('name')->get(),
        ]);
    }

    public function storeAthlete(Request $request): RedirectResponse
    {
        $identity = app(RegistrationIdentity::class);

        $validated = $request->validate([
            ...$identity->fieldRules(),
            'name' => ['required', 'string', 'max:120'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['required', 'string', 'max:40'],
            'guardian_name' => ['nullable', 'string', 'max:120'],
            'club_id' => ['nullable', 'integer', Rule::exists('clubs', 'id')],
        ], [
            'phone.required' => 'Nomor WhatsApp wajib diisi supaya pengelola bisa menghubungi.',
        ]);

        $identity->assertComplete($validated);

        // Direktori hanya punya satu kolom untuk dokumen identitas, jadi NIK
        // dan nomor paspor disimpan pada tempat yang sama. Pembedanya ada di
        // hash-nya, yang tetap memakai nilai asli masing-masing dokumen.
        $normalized = $identity->normalize($validated);
        $document = $normalized['nik'] ?? $normalized['passport_number'];

        // Satu akun tidak boleh punya dua catatan atlet dengan identitas yang
        // sama, karena identitas itulah yang dipakai mengenali orang saat
        // pendaftaran lain.
        $alreadyRecorded = Athlete::query()
            ->where('owner_user_id', $request->user()->id)
            ->where('nik_hash', $normalized['nik_hash'] ?? $normalized['passport_hash'])
            ->exists();

        if ($alreadyRecorded) {
            throw ValidationException::withMessages([
                'nik' => 'Atlet dengan identitas tersebut sudah tercatat pada akun Anda.',
            ]);
        }

        Athlete::query()->create([
            'name' => $validated['name'],
            'gender' => $validated['gender'],
            'date_of_birth' => $validated['date_of_birth'],
            'address' => $validated['address'] ?? null,
            'phone' => $validated['phone'],
            'guardian_name' => $validated['guardian_name'] ?? null,
            'club_id' => $validated['club_id'] ?? null,
            'nik' => $document,
            'nik_hash' => $normalized['nik_hash'] ?? $normalized['passport_hash'],
            'owner_user_id' => $request->user()->id,
        ]);

        return redirect()->route('directory.index')
            ->with('status', 'Atlet berhasil didaftarkan. Biodata ini bisa dipakai lagi saat mendaftar lomba lain.');
    }
}
