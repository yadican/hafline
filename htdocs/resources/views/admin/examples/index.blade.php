@extends('admin.layouts.app')

@section('content')
    <div class="panel-wrap">
        <div class="panel">
            @include('admin.layouts.partials.panel-heading', ['heading' => 'Örnek kayıtlar'])
            <div class="panel-body">
                @include('admin.examples.partials.index.toolbar')
                <div class="panel-list-scroll">
                    <table class="panel-table panel-list">
                        @include('admin.examples.partials.index.table-head')
                        @include('admin.examples.partials.index.table-body')
                    </table>
                </div>
                @include('admin.examples.partials.index.pagination')
            </div>
        </div>
    </div>
@endsection
