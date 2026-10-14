@extends('admin.layouts.app')

@section('content')
    <div class="panel-wrap haul-list">
        <div class="panel">
            @include('admin.layouts.partials.panel-heading', [
                'heading' => 'Taşıma fişleri',
                'icon' => 'fa-solid fa-truck-ramp-box',
                'description' => 'Taşıma fişlerini görüntüleyin, arayın ve yönetin.',
                'breadcrumbs' => [
                    'Gösterge paneli' => route('admin.dashboard'),
                    'Taşıma fişleri' => null,
                ],
            ])
            <div class="panel-body">
                <div x-data="{ searchOpen: false }">
                    @include('admin.hauls.partials.index.toolbar')
                    @include('admin.hauls.partials.index.search')
                </div>
                <div class="panel-list-scroll">
                    <table class="panel-table panel-list haul-table">
                        @include('admin.hauls.partials.index.table-head')
                        @include('admin.hauls.partials.index.table-body')
                    </table>
                </div>
                @include('admin.hauls.partials.index.pagination')
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/hauls.js')
@endpush
