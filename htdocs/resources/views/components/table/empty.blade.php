@props(['colspan', 'title', 'icon' => 'fa-solid fa-folder-open', 'text' => null])

<tr class="panel-empty-row">
    <td colspan="{{ $colspan }}">
        <div class="panel-empty">
            <span class="panel-empty-icon" aria-hidden="true"><i class="{{ $icon }}"></i></span>
            <div class="panel-empty-title">{{ $title }}</div>
            @if ($text)
                <p class="panel-empty-text">{{ $text }}</p>
            @endif
            {{ $slot }}
        </div>
    </td>
</tr>
