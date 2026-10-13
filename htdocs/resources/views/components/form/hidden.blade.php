@props(['name', 'value' => null, 'model' => null])

<input type="hidden" name="{{ $name }}" value="{{ old($name, $value ?? data_get($model, $name)) }}">
