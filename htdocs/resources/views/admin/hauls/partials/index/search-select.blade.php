<select id="f-{{ $key }}" name="f[{{ $key }}]" class="slct" aria-label="{{ $label }}">
    <option value="">{{ $placeholder }}</option>
    @foreach ($items as $value => $text)
        <option value="{{ $value }}" @selected((string) ($filters[$key] ?? '') === (string) $value)>{{ $text }}</option>
    @endforeach
</select>
