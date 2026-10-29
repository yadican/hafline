@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Çek / senet detayı"
        :icon="$instrument->payment_method->icon()"
        :description="$instrument->direction->label().' '.mb_strtolower($instrument->payment_method->label(), 'UTF-8').($instrument->number ? ' · No '.$instrument->number : '').' · vade '.$instrument->due_on->format('d.m.Y')"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Cari' => null, 'Çek / Senet' => route('admin.financial-instruments.index'), 'Detay' => null]"
        :back="route('admin.financial-instruments.index')"
    >
        @if ($source)
            <x-slot:actions>
                <x-ui.button icon="fa-solid fa-right-left" :href="route('admin.cari-entries.show', ['kind' => $source['kind']->value, 'entry' => $source['id']])">Cari hareketi</x-ui.button>
            </x-slot:actions>
        @endif

        @include('admin.financial-instruments.partials.show.details')
        @include('admin.financial-instruments.partials.show.photos')
        @include('admin.financial-instruments.partials.show.movements')
    </x-panel.page>
@endsection
