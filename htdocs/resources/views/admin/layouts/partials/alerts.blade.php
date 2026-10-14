<script type="application/json" id="panel-flash">@json(['success' => session('success'), 'error' => session('error'), 'info' => session('info')])</script>

@if ($errors->any())
    <div class="panel-alert panel-alert-warn" role="alert">
        <span class="panel-alert-icon" aria-hidden="true"><i class="fa-solid fa-triangle-exclamation"></i></span>
        <div class="panel-alert-body">
            <div class="panel-alert-title">Eksik veya hatalı bilgi</div>
            <div class="panel-alert-text">Aşağıdaki alanları kontrol ediniz.</div>
            <ul class="panel-alert-list">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
