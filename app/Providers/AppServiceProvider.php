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
        $this->shareInlinesiaWhatsappLink();
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
     * Tautan WhatsApp Inlinesia untuk kartu kemitraan di beranda. Jika nomor
     * belum diisi, tombol kembali ke email sampai CONTACT_WHATSAPP tersedia.
     */
    private function shareInlinesiaWhatsappLink(): void
    {
        View::composer('publik.beranda', function (ViewContract $view): void {
            $digits = preg_replace('/\D+/', '', (string) config('contact.whatsapp', '')) ?? '';
            if (str_starts_with($digits, '0')) {
                $digits = '62'.substr($digits, 1);
            } elseif (str_starts_with($digits, '8')) {
                $digits = '62'.$digits;
            }

            $url = strlen($digits) >= 8 && strlen($digits) <= 15
                ? 'https://wa.me/'.$digits.'?text='.rawurlencode('Halo Inlinesia, saya ingin bertanya tentang sponsorship dan iklan.')
                : 'mailto:'.config('contact.email').'?subject='.urlencode('Sponsor dan Iklan Inlinesia');

            $view->with('inlinesiaWhatsappUrl', $url);
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
