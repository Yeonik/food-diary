@props(['eaten', 'goal' => null])

{{-- The day's calorie total. With a goal → a teal progress ring showing what is
     left; without one → the plain eaten number. The ring NEVER changes colour,
     even past the goal — remaining is information, not a verdict (hard rule 4).
     Geometry transcribed from design/build (150×150, r66, 13px stroke). --}}
@php
    $hasGoal = ! is_null($goal);
    $remaining = $hasGoal ? $goal - $eaten : null;
    $r = 66;
    $c = 2 * M_PI * $r;
    $pct = $hasGoal && $goal > 0 ? min(1, $eaten / $goal) : 0;
    $dash = round($c * $pct);
    $full = round($c);
@endphp

<div class="rc">
    @if ($hasGoal)
        <div class="ring-box">
            <svg width="150" height="150" viewBox="0 0 150 150" aria-hidden="true">
                <circle cx="75" cy="75" r="{{ $r }}" fill="none" stroke="#eef1f1" stroke-width="13"/>
                <circle cx="75" cy="75" r="{{ $r }}" fill="none" stroke="var(--primary)" stroke-width="13"
                        stroke-linecap="round" stroke-dasharray="{{ $dash }} {{ $full }}" transform="rotate(-90 75 75)"/>
            </svg>
            <div class="ring-center">
                <div class="ring-num">{{ \App\Support\Format::kcal($remaining) }}</div>
                <div class="ring-sub">{{ __('day.remaining') }}</div>
            </div>
        </div>
        <div class="ring-total">{{ \App\Support\Format::kcal($eaten) }} {{ __('day.of_target', ['target' => \App\Support\Format::kcal($goal)]) }}</div>
    @else
        <div class="ring-plain">
            <div class="ring-sub">{{ __('day.eaten_today') }}</div>
            <div class="ring-eaten">{{ \App\Support\Format::kcal($eaten) }}</div>
            <div class="ring-sub">{{ __('nutrition.kcal') }}</div>
        </div>
    @endif
</div>
