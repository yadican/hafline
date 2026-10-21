@props(['items'])

{{ $items->onEachSide(2)->links('admin.partials.pager') }}
