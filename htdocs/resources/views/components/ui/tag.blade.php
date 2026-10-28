@props(['tag'])

<span {{ $attributes->class(['panel-tag', 'panel-tag-'.$tag->tone->value]) }}>{{ $tag->name }}</span>
