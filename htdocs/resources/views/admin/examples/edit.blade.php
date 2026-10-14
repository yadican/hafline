@extends('admin.layouts.app')

@section('content')
    <div class="panel-wrap">
        <div class="panel">
            @include('admin.layouts.partials.panel-heading', [
                'heading' => 'Örnek kaydı düzenle',
                'icon' => 'fa-solid fa-pen-to-square',
                'description' => $example->title.' kaydının bilgilerini güncelleyin.',
                'breadcrumbs' => [
                    'Gösterge paneli' => route('admin.dashboard'),
                    'Örnek kayıtlar' => route('admin.examples.index'),
                    'Düzenle' => null,
                ],
                'back' => route('admin.examples.index'),
            ])
            <div class="panel-body">
                @include('admin.examples.partials.edit.form')
            </div>
        </div>
    </div>
@endsection
