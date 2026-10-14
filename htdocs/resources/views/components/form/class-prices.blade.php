{{-- Her araç sınıfı için bir fiyat alanı; boş bırakılan sınıfın fiyatı kaldırılır --}}
@props(['classes', 'prices' => [], 'name' => 'class_prices'])

@if ($classes === [])
    <p class="panel-hint">
        Henüz araç sınıfı yok.
        <a href="{{ route('admin.vehicle-classes.create') }}">Araç sınıfı ekleyin</a>.
    </p>
@else
    <div class="panel-price-grid">
        @foreach ($classes as $classId => $className)
            <x-form.money :name="$name.'.'.$classId" :label="$className" :value="$prices[$classId] ?? null" placeholder="—" />
        @endforeach
    </div>
    {{ $slot }}
@endif
