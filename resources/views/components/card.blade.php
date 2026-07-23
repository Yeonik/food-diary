@props(['dim' => false])

{{-- The base surface: white, hairline border, 18px radius (design/build, .card).
     `dim` is the goal card when no goal is set — visibly quieter, still usable. --}}
<div {{ $attributes->merge(['class' => 'card'.($dim ? ' dim' : '')]) }}>{{ $slot }}</div>
