@props(['days' => [], 'goal' => null, 'label' => ''])

{{-- Calories per day, hand-rolled SVG. One colour for every bar whatever the
     value — a bar over the goal line does NOT redden or change colour, that
     would be a verdict (hard rule 4). The goal line is a dashed reference, drawn
     only when a goal is set; it judges nothing. A day with no entries is a zero,
     kept in place as a faint tick so the time axis stays continuous. --}}
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

    $baseY = 190;
    $plotH = 180;
    $width = 600;
    $slot = $count > 0 ? $width / $count : $width;
    $barW = min($slot * 0.62, 40);
    $showLabels = $count > 0 && $count <= 10;
@endphp

<svg class="chart" viewBox="0 0 600 210" role="img" aria-label="{{ $label }}">
    @foreach ($days as $i => $day)
        @php
            $scaled = ($day['kcal'] / $ceiling) * $plotH;
            $height = $day['kcal'] === 0 ? 2 : max($scaled, 1);
            $x = $i * $slot + ($slot - $barW) / 2;
            $y = $baseY - $height;
        @endphp
        <rect class="chart__bar {{ $day['kcal'] === 0 ? 'chart__bar--empty' : '' }}"
              x="{{ round($x, 1) }}" y="{{ round($y, 1) }}"
              width="{{ round($barW, 1) }}" height="{{ round($height, 1) }}" rx="2">
            <title>{{ $day['date'] }}: {{ \App\Support\Format::kcal($day['kcal']) }}</title>
        </rect>
        @if ($showLabels)
            <text class="chart__axis" x="{{ round($i * $slot + $slot / 2, 1) }}" y="205" text-anchor="middle">{{ \Illuminate\Support\Carbon::parse($day['date'])->translatedFormat('D') }}</text>
        @endif
    @endforeach

    @if ($goal !== null)
        @php $goalY = $baseY - ($goal / $ceiling) * $plotH; @endphp
        <line class="chart__goal" x1="0" y1="{{ round($goalY, 1) }}" x2="600" y2="{{ round($goalY, 1) }}" />
    @endif
</svg>
