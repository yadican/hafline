@props(['action'])

<form method="GET" action="{{ $action }}" role="search" {{ $attributes }}>
    @if (filled(request('sort')))
        <input type="hidden" name="sort" value="{{ request('sort') }}">
        <input type="hidden" name="dir" value="{{ request('dir') === 'desc' ? 'desc' : 'asc' }}">
    @endif
    {{ $slot }}
</form>
