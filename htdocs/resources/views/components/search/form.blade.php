@props(['action'])

<form
    method="GET"
    action="{{ $action }}"
    role="search"
    {{ $attributes->class(['grid w-full grid-cols-1 items-center gap-2 md:grid-cols-12']) }}
>
    @if (filled(request('sort')))
        <input type="hidden" class="hidden" name="sort" value="{{ request('sort') }}">
        <input type="hidden" class="hidden" name="dir" value="{{ request('dir') === 'desc' ? 'desc' : 'asc' }}">
    @endif
    {{ $slot }}
</form>
