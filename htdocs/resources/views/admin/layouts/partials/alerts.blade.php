<script type="application/json" id="panel-flash">@json(['success' => session('success'), 'error' => session('error'), 'info' => session('info')])</script>

@if ($errors->any())
    <div class="panel-alert panel-alert-warn" role="alert" data-error-summary>
        <span class="panel-alert-icon" aria-hidden="true"><i class="fa-solid fa-triangle-exclamation"></i></span>
        <div class="panel-alert-body">
            <div class="panel-alert-title">
                İşlem tamamlanmadı: eksik veya hatalı bilgi
                <span class="panel-alert-count">{{ count($errors->keys()) }} alan</span>
            </div>
            <div class="panel-alert-text">Kırmızı ile işaretli alanları düzeltip tekrar gönderin.</div>
            <ul class="panel-alert-list">
                @foreach ($errors->messages() as $field => $messages)
                    @foreach ($messages as $message)
                        <li>
                            <button type="button" class="panel-alert-link" data-error-field="{{ $field }}">
                                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i> {{ $message }}
                            </button>
                        </li>
                    @endforeach
                @endforeach
            </ul>
        </div>
    </div>
@endif
