@extends('admin.layouts.page')

@section('page')
    <div class="panel-wrap">
        <div class="panel">
            <div class="panel-title">
                <span>Gösterge paneli</span>
                <span class="panel-meta">Hafriyat</span>
            </div>
            <div class="panel-body">
                <table class="panel-table">
                    <thead>
                        <tr>
                            <th>Modül</th>
                            <th>Alan</th>
                            <th>Durum</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($modules as $module)
                            <tr>
                                <td>{{ $module['label'] }}</td>
                                <td>{{ $module['hint'] }}</td>
                                <td><span class="panel-badge panel-badge-pasif"><i class="fa-solid fa-minus" aria-hidden="true"></i> Henüz kayıt yok</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
