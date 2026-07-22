@props(['protein' => 0, 'fat' => 0, 'carbs' => 0])

{{-- Macro split by calorie contribution: protein and carbs at 4 kcal/g, fat at
     9. A neutral proportion bar — no colour carries a judgement. --}}
@php
    $pKcal = (float) $protein * 4;
    $fKcal = (float) $fat * 9;
    $cKcal = (float) $carbs * 4;
    $total = $pKcal + $fKcal + $cKcal;
    $pct = fn (float $part): int => $total > 0 ? (int) round($part / $total * 100) : 0;
    $p = $pct($pKcal);
    $f = $pct($fKcal);
    $c = 100 - $p - $f; // absorb rounding drift so the three read as 100
@endphp

@if ($total > 0)
    <div class="macro" role="img"
         aria-label="{{ __('nutrition.p') }} {{ $p }}% {{ __('nutrition.f') }} {{ $f }}% {{ __('nutrition.c') }} {{ $c }}%">
        <div class="macro__seg--p" style="width: {{ $p }}%"></div>
        <div class="macro__seg--f" style="width: {{ $f }}%"></div>
        <div class="macro__seg--c" style="width: {{ $c }}%"></div>
    </div>
    <div class="macro__legend">
        <span class="macro__key"><span class="macro__swatch macro__seg--p"></span>{{ __('nutrition.protein') }} {{ $p }}%</span>
        <span class="macro__key"><span class="macro__swatch macro__seg--f"></span>{{ __('nutrition.fat') }} {{ $f }}%</span>
        <span class="macro__key"><span class="macro__swatch macro__seg--c"></span>{{ __('nutrition.carbs') }} {{ $c }}%</span>
    </div>
@endif
