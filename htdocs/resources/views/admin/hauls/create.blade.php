@extends('admin.layouts.app')

@section('content')
    <div class="panel-wrap">
        <div class="panel">
            @include('admin.layouts.partials.panel-heading', [
                'heading' => 'Yeni taşıma fişi',
                'icon' => 'fa-solid fa-plus',
                'description' => 'Aynı fiş için birden fazla sefer girerseniz her sefer ayrı kayıt olur.',
                'breadcrumbs' => [
                    'Gösterge paneli' => route('admin.dashboard'),
                    'Taşıma fişleri' => route('admin.hauls.index'),
                    'Yeni fiş' => null,
                ],
                'back' => route('admin.hauls.index'),
            ])
            <div class="panel-body">
                @include('admin.hauls.partials.create.form')
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/hauls.js')
@endpush
