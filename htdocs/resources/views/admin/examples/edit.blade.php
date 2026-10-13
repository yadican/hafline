@extends('admin.layouts.app')

@section('content')
    <div class="panel-wrap">
        <div class="panel">
            @include('admin.layouts.partials.panel-heading', ['heading' => 'Örnek kaydı düzenle', 'meta' => 'Örnek'])
            <div class="panel-body">
                @include('admin.examples.partials.edit.form')
            </div>
        </div>
    </div>
@endsection
