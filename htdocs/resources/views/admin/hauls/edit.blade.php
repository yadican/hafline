@extends('admin.layouts.app')

@section('content')
    <div class="panel-wrap">
        <div class="panel">
            @include('admin.layouts.partials.panel-heading', [
                'heading' => 'Taşıma fişini düzenle',
                'icon' => 'fa-solid fa-pen-to-square',
                'description' => $haul->ticket_no.' numaralı fişi güncelleyin. Tutar, güncel güzergah fiyatlarıyla yeniden hesaplanır.',
                'breadcrumbs' => [
                    'Gösterge paneli' => route('admin.dashboard'),
                    'Taşıma fişleri' => route('admin.hauls.index'),
                    'Düzenle' => null,
                ],
                'back' => route('admin.hauls.index'),
            ])
            <div class="panel-body">
                @include('admin.hauls.partials.edit.form')
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/hauls.js')
@endpush
