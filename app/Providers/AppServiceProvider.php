<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewContract;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->shareCurrentBladeWithEveryView();
    }

    /**
     * Bagikan nama view halaman yang sedang dirender ke seluruh halaman.
     *
     * View komponen dilewati supaya komponen penunjuk lokasi blade tetap
     * menampilkan lokasi halaman, bukan lokasi berkas komponennya sendiri.
     */
    private function shareCurrentBladeWithEveryView(): void
    {
        View::composer('*', function (ViewContract $view): void {
            if (str_starts_with($view->name(), 'components.')) {
                return;
            }

            if (! View::shared('bladeLocation')) {
                View::share('bladeLocation', $view->name());
            }
        });
    }
}
