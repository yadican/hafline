{{-- Rapor indirme butonları (Excel / PDF / CSV) ve Yazdır; Yazdır, PDF'i yeni sekmede açar --}}
@props(['route', 'query' => []])

@foreach (\App\Enums\ReportFormat::cases() as $format)
    <x-ui.button
        :icon="$format->icon()"
        :href="route($route, ['format' => $format->value, ...$query])"
        class="report-export is-{{ $format->value }}"
    >
        {{ $format->label() }}
    </x-ui.button>
@endforeach
<x-ui.button
    icon="fa-solid fa-print"
    :href="route($route, ['format' => \App\Enums\ReportFormat::Pdf->value, 'print' => 1, ...$query])"
    class="report-export is-print"
    target="_blank"
    rel="noopener"
>
    Yazdır
</x-ui.button>
