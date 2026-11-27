<?php

namespace App\Http\Requests;

use App\Models\Sponsor;
use Illuminate\Foundation\Http\FormRequest;

class SponsorEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'brand_name' => ['required', 'string', 'max:120'],
            'contact_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:40', 'regex:/^(?=(?:\D*\d){8,15}\D*$)\+?[0-9\s().-]{8,40}$/'],
            'email' => ['nullable', 'email', 'max:180'],
            'package' => ['required', 'in:'.Sponsor::PACKAGE_EVENT_LOGO.','.Sponsor::PACKAGE_EVENT_MAIN],
            'website_url' => ['required', 'url', 'max:500'],
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'message' => ['nullable', 'string', 'max:500'],
            'agreement' => ['required', 'accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'event_id' => 'event',
            'brand_name' => 'nama brand',
            'contact_name' => 'nama kontak',
            'phone' => 'WhatsApp',
            'website_url' => 'link tujuan',
            'logo' => 'file logo/banner',
            'agreement' => 'persetujuan ketentuan',
        ];
    }
}
