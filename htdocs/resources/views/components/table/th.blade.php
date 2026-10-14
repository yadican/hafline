@props(['sort' => null, 'first' => 'asc'])

@php
    $current = null;

    if ($sort !== null && request('sort') === $sort) {
        $current = request('dir') === 'desc' ? 'desc' : 'asc';
    }

    $second = $first === 'asc' ? 'desc' : 'asc';
    $next = match ($current) {
        null => $first,
        $first => $second,
        default => null,
    };

    $label = match ($next) {
        'asc' => 'Artan sırala',
        'desc' => 'Azalan sırala',
        default => 'Sıralamayı kaldır',
    };
@endphp

<th
    {{ $attributes->class(['is-sortable' => $sort !== null, 'is-sorted' => $current !== null]) }}
    @if ($sort !== null) aria-sort="{{ match ($current) { 'asc' => 'ascending', 'desc' => 'descending', default => 'none' } }}" @endif
>
    @if ($sort === null)
        {{ $slot }}
    @else
        <a
            href="{{ request()->fullUrlWithQuery(['sort' => $next ? $sort : null, 'dir' => $next, 'page' => null]) }}"
            class="panel-sort-link"
            title="{{ $label }}"
        >
            <span class="panel-sort-label">{{ $slot }}</span>
            <span @class(['panel-sort-icon', 'is-asc' => $current === 'asc', 'is-desc' => $current === 'desc']) aria-hidden="true">
                @if ($current === null)
                    <i class="fa-solid fa-caret-up"></i>
                    <i class="fa-solid fa-caret-down"></i>
                @else
                    <i class="fa-solid fa-arrow-{{ $current === 'asc' ? 'up' : 'down' }}"></i>
                @endif
            </span>
        </a>
    @endif
</th>
