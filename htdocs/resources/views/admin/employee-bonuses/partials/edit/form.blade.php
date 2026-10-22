<form method="POST" action="{{ route('admin.employee-bonuses.update', $bonus) }}" data-employee-balance-form data-balance-url="{{ route('admin.employees.balance', ['employee' => '__ID__']) }}">
    @csrf
    @method('PUT')
    <x-form.grid :model="$bonus">
        @include('admin.employee-bonuses.partials.form.fields')
        <x-form.actions label="Güncelle" :cancel="route('admin.employee-bonuses.index')" />
    </x-form.grid>
</form>
