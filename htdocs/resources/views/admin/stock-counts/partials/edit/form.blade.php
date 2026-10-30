<form method="POST" action="{{ route('admin.stock-counts.update', $count) }}">
    @csrf
    @method('PUT')
    <x-form.grid :model="$count">
        @include('admin.stock-counts.partials.fields')
        <x-form.actions label="Güncelle" :cancel="route('admin.stock-counts.index')" />
    </x-form.grid>
</form>
