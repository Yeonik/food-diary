@props([
    'icon',
    'label',
    'route',
    'match' => null,        // route pattern for the active check; defaults to route
    'layout' => 'sidebar',  // sidebar (desktop rail) | tab (mobile bottom bar)
])

{{-- One navigation entry — a sidebar row on desktop, a tab-bar item on mobile.
     Mirrors the kit NavItem, emitting the existing shell classes. --}}
@php
    $active = request()->routeIs($match ?? $route);
    $class = $layout === 'tab' ? 'tabbar__item' : 'sidebar__item';
@endphp

<a href="{{ route($route) }}" class="{{ $class }}" @if ($active) aria-current="page" @endif>
    <x-icon :name="$icon" />
    <span>{{ $label }}</span>
</a>
