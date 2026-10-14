<div class="panel-liste-bar">
    <form method="GET" action="{{ route('admin.examples.index') }}" role="search">
        <input
            type="search"
            name="q"
            value="{{ $filters['q'] ?? '' }}"
            class="slct"
            placeholder="Başlık, e-posta, telefon…"
            aria-label="Ara"
            maxlength="100"
        >

        <select name="active" class="slct" aria-label="Durum">
            <option value="">Tüm durumlar</option>
            <option value="1" @selected(($filters['active'] ?? null) === '1')>Aktif</option>
            <option value="0" @selected(($filters['active'] ?? null) === '0')>Pasif</option>
        </select>

        <select name="shift" class="slct" aria-label="Vardiya">
            <option value="">Tüm vardiyalar</option>
            @foreach (\App\Enums\ExampleShift::options() as $value => $label)
                <option value="{{ $value }}" @selected(($filters['shift'] ?? null) === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <button type="submit" class="panel-btn">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Ara
        </button>

        @if (! empty($filters))
            <a href="{{ route('admin.examples.index') }}" class="panel-btn">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i> Temizle
            </a>
        @endif
        <a href="{{ route('admin.examples.create') }}" class="panel-btn">
            <i class="fa-solid fa-plus" aria-hidden="true"></i> Yeni kayıt
        </a>
    </form>
</div>
