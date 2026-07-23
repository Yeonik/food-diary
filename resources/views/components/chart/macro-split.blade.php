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
    <div class="split" role="img"
         aria-label="{{ __('nutrition.p') }} {{ $p }}% {{ __('nutrition.f') }} {{ $f }}% {{ __('nutrition.c') }} {{ $c }}%">
        <div class="split-p" style="width: {{ $p }}%"></div>
        <div class="split-f" style="width: {{ $f }}%"></div>
        <div class="split-c" style="width: {{ $c }}%"></div>
    </div>
    <div class="split-legend">
        <span>{{ __('nutrition.p') }} {{ $p }}%</span>
        <span>{{ __('nutrition.f') }} {{ $f }}%</span>
        <span>{{ __('nutrition.c') }} {{ $c }}%</span>
    </div>
@endif
