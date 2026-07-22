@props(['eaten' => 0, 'target' => 0])

{{-- Progress ring, variant A. The colour never changes with the number: it is
     the primary fill on its tint track whether the day is under or over the
     target (hard rule 4 — a ring that turns red is a verdict). It is only ever
     rendered when a target exists; with no goal the caller shows a plain figure
     instead, because there is no proportion to draw. --}}
@php
    $eaten = (float) $eaten;
    $target = (float) $target;
    $circumference = 2 * M_PI * 52;
    $proportion = $target > 0 ? min($eaten / $target, 1) : 0;
    $offset = $circumference * (1 - $proportion);
    $eatenLabel = \App\Support\Format::kcal($eaten);
    $targetLabel = \App\Support\Format::kcal($target);
@endphp

<svg class="ring" viewBox="0 0 120 120" width="150" height="150" role="img"
     aria-label="{{ __('day.eaten_today') }}: {{ $eatenLabel }} {{ __('day.of_target', ['target' => $targetLabel]) }}">
    <circle class="ring__track" cx="60" cy="60" r="52" stroke-width="12" />
    <circle class="ring__value" cx="60" cy="60" r="52" stroke-width="12"
            stroke-dasharray="{{ round($circumference, 2) }}"
            stroke-dashoffset="{{ round($offset, 2) }}" />
    <text class="ring__num" x="60" y="57" text-anchor="middle" dominant-baseline="middle">{{ $eatenLabel }}</text>
    <text class="ring__cap" x="60" y="80" text-anchor="middle">{{ __('day.of_target', ['target' => $targetLabel]) }}</text>
</svg>
