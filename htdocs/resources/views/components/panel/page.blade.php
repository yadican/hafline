@props(['heading', 'icon' => null, 'description' => null, 'breadcrumbs' => [], 'back' => null, 'meta' => null])

<div {{ $attributes->class(['panel-wrap']) }}>
    <div class="panel">
        <x-panel.heading :$heading :$icon :$description :$breadcrumbs :$back :$meta />
        <div class="panel-body">
            {{ $slot }}
        </div>
    </div>
</div>
