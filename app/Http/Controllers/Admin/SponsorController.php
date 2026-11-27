<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sponsor;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SponsorController extends Controller
{
    public function index(): View
    {
        return view('admin.sponsors.index', [
            'sponsors' => Sponsor::query()
                ->with(['event', 'reviewer'])
                ->latest()
                ->get(),
        ]);
    }

    public function approve(Sponsor $sponsor): RedirectResponse
    {
        $sponsor->update([
            'status' => Sponsor::STATUS_APPROVED,
            'reviewed_by' => request()->user()->id,
            'reviewed_at' => now(),
        ]);

        return redirect()->route('admin.sponsors.index')
            ->with('status', "Sponsor {$sponsor->brand_name} disetujui dan tampil sesuai periode.");
    }

    public function reject(Sponsor $sponsor): RedirectResponse
    {
        $sponsor->update([
            'status' => Sponsor::STATUS_REJECTED,
            'reviewed_by' => request()->user()->id,
            'reviewed_at' => now(),
        ]);

        return redirect()->route('admin.sponsors.index')
            ->with('status', "Pengajuan sponsor {$sponsor->brand_name} ditolak.");
    }
}
