@props(['days' => [], 'goal' => null, 'label' => '', 'height' => 150])

{{-- Calories per day, hand-rolled SVG. Geometry from design/build: bars at most
     80 units wide with a 7 radius on a 900-unit box, drawn 150px tall whatever
     the card's width, so the two History charts line up side by side.

     The day labels are HTML under the chart, not <text> inside it. The box is
     stretched to the card, and stretched text is squashed text.

     One colour for every bar whatever the value — a bar over the goal line does
     NOT redden or change colour, that would be a verdict (hard rule 4). The goal
     line is a dashed reference, drawn only when a goal is set; it judges nothing.
     A day with no entries is a zero, kept in place as a faint tick so the time
     axis stays continuous. --}}
@php
    $count = count($days);
    $goal = $goal !== null ? (int) $goal : null;

    $ceiling = 0;
    foreach ($days as $day) {
        $ceiling = max($ceiling, $day['kcal']);
    }
    if ($goal !== null) {
        $ceiling = max($ceiling, $goal);
    }
    $ceiling = $ceiling > 0 ? $ceiling : 1;

    $width = 900;
    $baseY = 220;
    $plotH = 175;
    $slot = $count > 0 ? $width / $count : $width;
    $barW = min($slot * 0.62, 80);
    $showLabels = $count > 0 && $count <= 10;
@endphp

<svg class="chart" width="100%" height="{{ $height }}" viewBox="0 0 {{ $width }} {{ $baseY }}"
     preserveAspectRatio="none" role="img" aria-label="{{ $label }}">
    @foreach ($days as $i => $day)
        @php
            $scaled = ($day['kcal'] / $ceiling) * $plotH;
            $barH = $day['kcal'] === 0 ? 3 : max($scaled, 1);
            $x = $i * $slot + ($slot - $barW) / 2;
        @endphp
        <rect class="chart__bar {{ $day['kcal'] === 0 ? 'chart__bar--empty' : '' }}"
              x="{{ round($x, 1) }}" y="{{ round($baseY - $barH, 1) }}"
              width="{{ round($barW, 1) }}" height="{{ round($barH, 1) }}" rx="7">
            <title>{{ $day['date'] }}: {{ \App\Support\Format::kcal($day['kcal']) }}</title>
        </rect>
    @endforeach

    @if ($goal !== null)
        @php $goalY = $baseY - ($goal / $ceiling) * $plotH; @endphp
        <line class="chart__goal" x1="0" y1="{{ round($goalY, 1) }}" x2="{{ $width }}" y2="{{ round($goalY, 1) }}" />
    @endif
</svg>

@if ($showLabels)
    <div class="chart-axis" aria-hidden="true">
        @foreach ($days as $day)
            <span>{{ \Illuminate\Support\Carbon::parse($day['date'])->translatedFormat('D') }}</span>
        @endforeach
    </div>
@endif
