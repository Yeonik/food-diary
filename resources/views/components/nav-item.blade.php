@props([
    'icon',
    'label',
    'route',
    'match' => null,        // route pattern for the active check; defaults to route
    'layout' => 'sidebar',  // sidebar (desktop rail) | tab (mobile bottom bar)
])

{{-- One navigation entry — a sidebar row on desktop, a tab-bar item on mobile
     (design/build, .nav and .tabbar button). --}}
@php $active = request()->routeIs($match ?? $route); @endphp

@php $class = trim(($layout === 'tab' ? '' : 'nav').($active ? ' on' : '')); @endphp

<a href="{{ route($route) }}" class="{{ $class }}"
   @if ($active) aria-current="page" @endif>
    <x-icon :name="$icon" />
    <span>{{ $label }}</span>
</a>
