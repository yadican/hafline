@props(['legend'])

<fieldset class="panel-form-group">
    <legend>{{ $legend }}</legend>
    <div class="panel-form-group-body">
        {{ $slot }}
    </div>
</fieldset>
