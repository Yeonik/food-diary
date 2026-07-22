@props([
    'variant' => 'primary',   // primary | secondary | ghost | danger
    'size' => 'md',           // sm | md | lg
    'full' => false,
    'icon' => null,           // optional leading icon name
    'href' => null,           // renders an <a> when set, a <button> otherwise
    'type' => 'submit',
])

{{-- CTA and secondary actions. Mirrors the kit Button. --}}
@php
    $classes = 'btn'
        .($variant !== 'primary' ? ' btn--'.$variant : '')
        .($size !== 'md' ? ' btn--'.$size : '')
        .($full ? ' btn--block' : '');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>@if ($icon)<x-icon :name="$icon" />@endif{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>@if ($icon)<x-icon :name="$icon" />@endif{{ $slot }}</button>
@endif
