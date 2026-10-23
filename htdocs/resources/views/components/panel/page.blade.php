@props(['heading', 'icon' => null, 'description' => null, 'breadcrumbs' => [], 'back' => null, 'meta' => null])

<div {{ $attributes->class(['panel-wrap']) }}>
    <div class="panel">
        <x-panel.heading :$heading :$icon :$description :$breadcrumbs :$back :$meta>
            @isset($actions)
                <x-slot:actions>{{ $actions }}</x-slot:actions>
            @endisset
        </x-panel.heading>
        <div class="panel-body">
            {{ $slot }}
        </div>
    </div>
</div>
