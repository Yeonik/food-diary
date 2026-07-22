@props([
    'kind' => 'protein',   // protein | fat | carb — tint only, never a verdict
    'label',
    'value',               // grams, numeric — formatted here for the locale
    'goal' => null,        // omit → no bar, value alone
])

{{-- A macro line in the day summary, tinted by category. Mirrors the kit
     MacroRow. --}}
@php
    $classes = 'macro-row'.($kind === 'fat' ? ' macro-row--fat' : ($kind === 'carb' ? ' macro-row--carb' : ''));
    $pct = ! is_null($goal) && $goal > 0 ? min(100, round(($value / $goal) * 100)) : null;
@endphp

<div class="{{ $classes }}">
    <div class="macro-row__head">
        <span>{{ $label }}</span>
        <span class="macro-row__value">{{ \App\Support\Format::macro($value) }}@if (! is_null($goal)) / {{ \App\Support\Format::macro($goal) }}@endif {{ __('nutrition.g') }}</span>
    </div>
    @if (! is_null($goal))
        <div class="macro-row__track"><div class="macro-row__fill" style="width: {{ $pct }}%"></div></div>
    @endif
</div>
