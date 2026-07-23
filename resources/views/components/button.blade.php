@props([
    'variant' => 'primary',   // primary | secondary | ghost | danger
    'full' => false,
    'icon' => null,           // optional leading icon name
    'href' => null,           // renders an <a> when set, a <button> otherwise
    'type' => 'submit',
])

{{-- CTA and secondary actions (design/build/app.css, .btn). --}}
@php
    $suffix = ['primary' => 'p', 'secondary' => 's', 'ghost' => 'g', 'danger' => 'd'][$variant] ?? 'p';
    $classes = 'btn btn-'.$suffix.($full ? ' btn-block' : '');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>@if ($icon)<x-icon :name="$icon" />@endif{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>@if ($icon)<x-icon :name="$icon" />@endif{{ $slot }}</button>
@endif
