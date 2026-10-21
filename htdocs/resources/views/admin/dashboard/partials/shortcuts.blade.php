<div class="dash-shortcuts">
    @foreach ($shortcuts as $shortcut)
        <a href="{{ route($shortcut['route']) }}" class="dash-shortcut">
            <i class="{{ $shortcut['icon'] }}" aria-hidden="true"></i>
            <span>{{ $shortcut['label'] }}</span>
        </a>
    @endforeach
</div>
