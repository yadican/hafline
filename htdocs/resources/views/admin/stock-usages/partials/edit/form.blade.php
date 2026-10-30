<form method="POST" action="{{ route('admin.stock-usages.update', $usage) }}">
    @csrf
    @method('PUT')
    <x-form.grid :model="$usage">
        @include('admin.stock-usages.partials.fields')
        <x-form.actions label="Güncelle" :cancel="route('admin.stock-usages.index')" />
    </x-form.grid>
</form>
