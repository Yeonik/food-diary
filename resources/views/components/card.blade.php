@props(['pad' => 'roomy', 'variant' => null])

{{-- The base surface: white, hairline border, soft shadow. Mirrors the kit Card.
     variant: meal (r20) | summary (r24) | dim (the goal card when off). --}}
@php
    $classes = 'card'
        .($pad === 'compact' ? ' card--compact' : '')
        .($variant ? ' card--'.$variant : '');
@endphp

<div {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</div>
