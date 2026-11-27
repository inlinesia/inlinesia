<?php

namespace App\Providers;

use App\Models\Sponsor;
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
        $this->shareActiveMainSponsors();
    }

    /**
     * Logo sponsor utama tampil di navbar dan footer publik selama masih
     * berada dalam periode aktif dan sudah disetujui admin.
     */
    private function shareActiveMainSponsors(): void
    {
        View::composer(['components.navbar', 'components.footer'], function (ViewContract $view): void {
            $view->with(
                'mainWebSponsors',
                Sponsor::query()
                    ->web()
                    ->approved()
                    ->active()
                    ->where('package', Sponsor::PACKAGE_WEB_MAIN)
                    ->get(),
            );
        });
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
