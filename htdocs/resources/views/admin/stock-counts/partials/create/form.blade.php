<form method="POST" action="{{ route('admin.stock-counts.store') }}">
    @csrf
    <x-form.grid :model="['counted_on' => now()->toDateString()]">
        @include('admin.stock-counts.partials.fields')
        <x-form.actions label="Kaydet" :cancel="route('admin.stock-counts.index')" />
    </x-form.grid>
</form>
