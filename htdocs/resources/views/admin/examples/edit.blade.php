@extends('admin.layouts.page')

@section('page')
    <div class="panel-wrap">
        <div class="panel">
            <div class="panel-title">
                <span>Örnek kaydı düzenle</span>
                <span class="panel-meta">Örnek</span>
            </div>
            <div class="panel-body">
                <form method="POST" action="{{ route('admin.examples.update', $example) }}" enctype="multipart/form-data">
                    @method('PUT')
                    @include('admin.examples._form', ['submitLabel' => 'Güncelle'])
                </form>
            </div>
        </div>
    </div>
@endsection
