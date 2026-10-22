<form method="POST" action="{{ route('admin.employee-bonuses.store') }}" data-employee-balance-form data-balance-url="{{ route('admin.employees.balance', ['employee' => '__ID__']) }}">
    @csrf
    <x-form.grid>
        @include('admin.employee-bonuses.partials.form.fields')
        <x-form.actions label="Kaydet" :cancel="route('admin.employee-bonuses.index')" />
    </x-form.grid>
</form>
