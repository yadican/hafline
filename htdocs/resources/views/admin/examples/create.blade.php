@extends('admin.layouts.page')

@section('page')
    <div class="panel-wrap">
        <div class="panel">
            <div class="panel-title">
                <span>Yeni örnek kayıt</span>
                <span class="panel-meta">Örnek</span>
            </div>
            <div class="panel-body">
                <form method="POST" action="{{ route('admin.examples.store') }}" enctype="multipart/form-data">
                    @include('admin.examples._form', ['submitLabel' => 'Kaydet'])
                </form>
            </div>
        </div>
    </div>
@endsection
