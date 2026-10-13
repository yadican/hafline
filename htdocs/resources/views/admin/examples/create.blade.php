@extends('admin.layouts.app')

@section('content')
    <div class="panel-wrap">
        <div class="panel">
            @include('admin.layouts.partials.panel-heading', ['heading' => 'Yeni örnek kayıt', 'meta' => 'Örnek'])
            <div class="panel-body">
                @include('admin.examples.partials.create.form')
            </div>
        </div>
    </div>
@endsection
