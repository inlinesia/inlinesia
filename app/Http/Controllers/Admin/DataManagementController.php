<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDataManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataManagementController extends Controller
{
    public function backup(): View
    {
        return view('admin.data.backup');
    }

    public function downloadBackup(AdminDataManagementService $dataManagement): StreamedResponse
    {
        $contents = $dataManagement->makeBackup();
        $filename = 'inlinesia-backup-'.now()->format('Ymd-His').'.json';

        return response()->streamDownload(
            static function () use ($contents): void {
                echo $contents;
            },
            $filename,
            ['Content-Type' => 'application/json; charset=UTF-8'],
        );
    }

    public function restore(Request $request, AdminDataManagementService $dataManagement): RedirectResponse
    {
        $validated = $request->validate([
            'backup' => ['required', 'file', 'mimes:json', 'max:102400'],
            'confirmed' => ['required', 'accepted'],
        ]);

        $contents = file_get_contents($validated['backup']->getRealPath());
        if (! is_string($contents)) {
            throw ValidationException::withMessages([
                'backup' => 'File backup gagal dibaca.',
            ]);
        }

        $dataManagement->restore($contents, (int) $request->user()->id, $request->session()->getId());

        return redirect()->route('admin.data.backup')
            ->with('status', 'Backup berhasil dipulihkan. Data aplikasi sekarang mengikuti isi backup tersebut.');
    }

    public function delete(Request $request, AdminDataManagementService $dataManagement): View
    {
        return view('admin.data.delete', [
            'catalogs' => $dataManagement->catalogs($request->session()->getId()),
        ]);
    }

    public function reviewDeletion(Request $request, AdminDataManagementService $dataManagement): View
    {
        $selected = $this->validatedSelection($request, $dataManagement);
        sort($selected);
        $snapshot = $dataManagement->deletionSnapshot($selected, $request->session()->getId());

        $request->session()->put('admin_data_deletion_review', [
            'selected' => $selected,
            'snapshot' => $snapshot,
            'reviewed_at' => now()->timestamp,
        ]);

        return view('admin.data.delete-confirmation', [
            'snapshot' => $snapshot,
        ]);
    }

    public function destroySelected(Request $request, AdminDataManagementService $dataManagement): RedirectResponse
    {
        $selected = $this->validatedSelection($request, $dataManagement);
        $request->validate([
            'confirmed' => ['required', 'accepted'],
        ]);

        $review = $request->session()->get('admin_data_deletion_review');
        $reviewedSelection = $review['selected'] ?? [];
        sort($selected);
        sort($reviewedSelection);

        abort_unless(
            $selected === $reviewedSelection
                && isset($review['reviewed_at'])
                && $review['reviewed_at'] >= now()->subMinutes(10)->timestamp,
            409,
            'Tinjauan penghapusan tidak berlaku. Silakan mulai kembali.',
        );

        $currentSnapshot = $dataManagement->deletionSnapshot($selected, $request->session()->getId());
        abort_unless(
            $currentSnapshot === ($review['snapshot'] ?? null),
            409,
            'Jumlah data berubah setelah ditinjau. Periksa ulang daftar data sebelum menghapus.',
        );

        $deleted = $dataManagement->deleteSelected($selected, $request->session()->getId());
        $request->session()->forget('admin_data_deletion_review');
        $summary = collect($deleted)
            ->map(fn (int $count, string $key): string => "{$review['snapshot'][$key]['label']}: {$count}")
            ->implode(', ');

        return redirect()->route('admin.data.delete')
            ->with('status', 'Katalog terpilih berhasil dihapus. Data dihapus: '.$summary.'.');
    }

    /**
     * @return list<string>
     */
    private function validatedSelection(Request $request, AdminDataManagementService $dataManagement): array
    {
        $allowed = array_keys($dataManagement->catalogs($request->session()->getId()));
        $validated = Validator::make($request->all(), [
            'selected' => ['required', 'array', 'min:1'],
            'selected.*' => ['required', 'string', 'distinct', Rule::in($allowed)],
        ])->validate();

        return array_values($validated['selected']);
    }
}
