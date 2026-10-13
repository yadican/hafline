@props(['label', 'for' => null])

<tr>
    <th>
        @if ($label === '')
            &nbsp;
        @elseif ($for)
            <label for="{{ $for }}">{{ $label }}</label>
        @else
            {{ $label }}
        @endif
    </th>
    <td {{ $attributes }}>{{ $slot }}</td>
</tr>
