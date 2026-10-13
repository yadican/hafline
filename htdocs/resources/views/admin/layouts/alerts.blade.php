@if (session('success'))
    <div class="panel-alert panel-alert-ok" role="status">
        <span class="panel-alert-icon" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></span>
        <div class="panel-alert-body">
            <div class="panel-alert-title">Tamamlandı</div>
            <div class="panel-alert-text">{{ session('success') }}</div>
        </div>
    </div>
@endif

@if (session('error'))
    <div class="panel-alert panel-alert-warn" role="alert">
        <span class="panel-alert-icon" aria-hidden="true"><i class="fa-solid fa-triangle-exclamation"></i></span>
        <div class="panel-alert-body">
            <div class="panel-alert-title">İşlem tamamlanamadı</div>
            <div class="panel-alert-text">{{ session('error') }}</div>
        </div>
    </div>
@endif

@if ($errors->any())
    <div class="panel-alert panel-alert-warn" role="alert">
        <span class="panel-alert-icon" aria-hidden="true"><i class="fa-solid fa-triangle-exclamation"></i></span>
        <div class="panel-alert-body">
            <div class="panel-alert-title">Eksik bilgi</div>
            <ul class="panel-alert-list">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
