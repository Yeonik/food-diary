@props([
    'tone' => 'neutral',   // neutral | danger | accent
    'label',               // accessible label — required (aria-label + title)
    'icon' => null,
    'href' => null,
    'type' => 'submit',
])

{{-- Compact square icon-only action — edit / delete on records. Mirrors the kit
     IconButton. --}}
@php $classes = 'icon-btn'.($tone !== 'neutral' ? ' icon-btn--'.$tone : ''); @endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }} aria-label="{{ $label }}" title="{{ $label }}">@if ($icon)<x-icon :name="$icon" />@endif{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }} aria-label="{{ $label }}" title="{{ $label }}">@if ($icon)<x-icon :name="$icon" />@endif{{ $slot }}</button>
@endif
