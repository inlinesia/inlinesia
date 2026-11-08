<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ThemeSettingController extends Controller
{
    /**
     * Daftar tema yang tersedia.
     *
     * @var list<string>
     */
    private const THEMES = ['glass', 'copper', 'neural', 'topic'];

    public function edit(): View
    {
        return view('admin.settings.theme');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'string', Rule::in(self::THEMES)],
        ]);

        return back()
            ->with('status', 'Tema '.$validated['theme'].' tersimpan. Pilihan berlaku di peramban ini.')
            ->with('theme', $validated['theme']);
    }
}
