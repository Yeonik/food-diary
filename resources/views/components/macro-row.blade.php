@props([
    'kind' => 'protein',   // protein | fat | carb — tint only, never a verdict
    'label',
    'value',               // grams, numeric — formatted here for the locale
    'goal' => null,        // omit → no bar, value alone
])

{{-- A macro line in the day summary, tinted by category (design/build, .macro).
     The bar and the "/ goal" half appear only when a goal is set. --}}
@php
    $tint = ['protein' => 'p', 'fat' => 'f', 'carb' => 'c'][$kind] ?? 'p';
    $classes = 'macro '.$tint;
    $pct = ! is_null($goal) && $goal > 0 ? min(100, round(($value / $goal) * 100)) : null;
@endphp

<div class="{{ $classes }}">
    <div class="mrow">
        <span>{{ $label }}</span>
        <b>{{ \App\Support\Format::macro($value) }}@if (! is_null($goal)) / {{ \App\Support\Format::macroGoal($goal) }}@endif {{ __('nutrition.g') }}</b>
    </div>
    @if (! is_null($goal))
        <div class="mbar"><div style="width: {{ $pct }}%"></div></div>
    @endif
</div>
