{{-- Cari bakiye: pozitif = borçlu (B, bize borçlu), negatif = alacaklı (A, biz borçluyuz) --}}
@props(['value'])

@php
    $amount = round((float) $value, 2);
@endphp

<span {{ $attributes->class(['panel-balance', 'is-debit' => $amount > 0, 'is-credit' => $amount < 0]) }}>
    {{ number_format(abs($amount), 2, ',', '.') }} TL
    @if ($amount > 0)
        <abbr title="Borçlu">B</abbr>
    @elseif ($amount < 0)
        <abbr title="Alacaklı">A</abbr>
    @endif
</span>
