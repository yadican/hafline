@props(['label', 'for' => null, 'name' => null])

@php
    $errorName = $name ?? $for;
    $error = null;

    if ($errorName && isset($errors)) {
        $error = $errors->first($errorName) ?: null;

        if ($error === null) {
            foreach ($errors->keys() as $key) {
                if (str_starts_with((string) $key, $errorName.'.')) {
                    $error = $errors->first($key) ?: null;
                    break;
                }
            }
        }
    }
@endphp

<div {{ $attributes->class(['panel-field', 'panel-field-invalid' => (bool) $error]) }}>
    @if ($label !== '')
        @if ($for)
            <label for="{{ $for }}">{{ $label }}</label>
        @else
            <span class="panel-field-name">{{ $label }}</span>
        @endif
    @endif
    {{ $slot }}
    @if ($error)
        <div class="panel-field-error">{{ $error }}</div>
    @endif
</div>
