<form method="POST" action="{{ route('admin.stock-usages.store') }}">
    @csrf
    <x-form.grid :model="['used_on' => now()->toDateString()]">
        @include('admin.stock-usages.partials.fields')
        <x-form.actions label="Kaydet" :cancel="route('admin.stock-usages.index')" />
    </x-form.grid>
</form>
