@extends('admin.layouts.app')

@section('content')
    <div class="panel-wrap">
        <div class="panel">
            @include('admin.layouts.partials.panel-heading', [
                'heading' => 'Gösterge paneli',
                'icon' => 'fa-solid fa-gauge',
                'description' => 'Modüllere hızlı erişim ve genel durum.',
            ])
            <div class="panel-body">
                @include('admin.dashboard.partials.modules')
            </div>
        </div>
    </div>
@endsection
