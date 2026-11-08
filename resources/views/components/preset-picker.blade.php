@props([
    'name',
    'label',
    'options' => [],
    'custom' => 'Tambah pilihan lain',
    'value' => null,
    'rows' => 5,
    'maxlength' => 5000,
    'required' => true,
    'hint' => null,
])

@php
    $currentLines = collect(preg_split('/\R/u', (string) ($value ?? '')))
        ->map(fn(string $line) => trim($line))
        ->filter()
        ->values()
        ->all();

    $checkedOptions = array_values(array_intersect($options, $currentLines));
    $customLines = array_values(array_diff($currentLines, $options));
@endphp

<div class="preset-picker" data-preset-picker>
    <span class="preset-picker-label">{{ $label }} @if ($required)
            <span>*</span>
        @endif
    </span>

    @if ($options)
        <div class="preset-options" role="group" aria-label="Pilihan cepat {{ $label }}">
            @foreach ($options as $option)
                <label class="preset-option">
                    <input type="checkbox" data-preset-value="{{ $option }}" @checked(in_array($option, $checkedOptions, true))>
                    <span>{{ $option }}</span>
                </label>
            @endforeach
        </div>
    @endif

    <label class="preset-option preset-option-custom">
        <input type="checkbox" data-preset-custom-toggle @checked($customLines !== [])>
        <span>{{ $custom }}</span>
    </label>

    <div class="preset-custom-area" data-preset-custom-area @if ($customLines === []) hidden @endif>
        <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $rows }}" maxlength="{{ $maxlength }}"
            data-preset-target @if ($required) required @endif>{{ old($name, implode("\n", $currentLines)) }}</textarea>

        @if ($hint)
            <small class="field-hint">{{ $hint }}</small>
        @endif

        @error($name)
            <small class="field-error">{{ $message }}</small>
        @enderror
    </div>

    <p class="preset-picker-note" data-preset-note @if ($customLines !== []) hidden @endif>
        Centang pilihan di atas, atau pilih “{{ $custom }}” untuk menulis sendiri.
    </p>
</div>
