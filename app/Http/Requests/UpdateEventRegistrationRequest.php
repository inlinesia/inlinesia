<?php

namespace App\Http\Requests;

use App\Models\Event;
use App\Services\RegistrationIdentity;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $event instanceof Event && $event->isManagedBy($this->user());
    }

    public function rules(): array
    {
        /** @var Event $event */
        $event = $this->route('event');

        return [
            ...app(RegistrationIdentity::class)->fieldRules(),
            'participant_name' => ['required', 'string', 'max:120'],
            'nickname' => ['nullable', 'string', 'max:80'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'address' => ['required', 'string', 'max:1000'],
            'guardian_name' => ['required', 'string', 'max:120'],
            'whatsapp' => ['required', 'string', 'max:40'],
            'club_name' => ['nullable', 'string', 'max:180'],
            'races' => ['required', 'array', 'min:1', 'distinct'],
            'races.*' => ['required', 'string', Rule::in($event->races)],
            'status' => ['required', Rule::in(['pending', 'approved', 'rejected'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            app(RegistrationIdentity::class)->assertComplete($this->input());
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'passport_number' => 'nomor paspor',
            'nationality' => 'negara asal',
        ];
    }
}
