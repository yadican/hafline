@extends('admin.layouts.app')

@section('content')
    <div class="panel-wrap">
        <div class="panel">
            @include('admin.layouts.partials.panel-heading', [
                'heading' => 'Yeni örnek kayıt',
                'icon' => 'fa-solid fa-plus',
                'description' => 'Yeni bir kayıt oluşturmak için formu doldurun.',
                'breadcrumbs' => [
                    'Gösterge paneli' => route('admin.dashboard'),
                    'Örnek kayıtlar' => route('admin.examples.index'),
                    'Yeni kayıt' => null,
                ],
                'back' => route('admin.examples.index'),
            ])
            <div class="panel-body">
                @include('admin.examples.partials.create.form')
            </div>
        </div>
    </div>
@endsection
