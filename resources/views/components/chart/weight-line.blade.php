@props([
    'points' => [],
    'label' => '',
    'box' => '0 0 900 200',   // must match the box WeightSeries laid the points out in
    'height' => 150,
    'grid' => false,          // faint horizontal rules, for the tall Weight-screen line
])

@php
    // The box's own width and height, so the scale divides whatever box it is given.
    [, , $boxW, $boxH] = array_map(floatval(...), explode(' ', $box) + [0, 0, 900, 200]);

    // The scale is read back off the points rather than recomputed: they were laid
    // out linearly, so the highest reading's y IS the top of the drawn range and the
    // lowest reading's y is the bottom. Nothing here can drift out of step with the
    // line, because it is measured from the line.
    $scale = [];
    $ticks = [];

    if (count($points) > 0) {
        $highest = $lowest = $points[0];
        foreach ($points as $point) {
            $highest = $point['value'] > $highest['value'] ? $point : $highest;
            $lowest = $point['value'] < $lowest['value'] ? $point : $lowest;
        }

        $scale = $highest['value'] > $lowest['value']
            ? [
                [$highest['value'], $highest['y']],
                [($highest['value'] + $lowest['value']) / 2, ($highest['y'] + $lowest['y']) / 2],
                [$lowest['value'], $lowest['y']],
            ]
            // Every reading the same: one number, and no range to divide.
            : [[$highest['value'], $highest['y']]];

        // Up to four dates, evenly spaced, always including the first and the last.
        $last = count($points) - 1;
        $wanted = min(count($points), 4);
        $indexes = $wanted > 1
            ? array_map(fn (int $i): int => (int) round($i * $last / ($wanted - 1)), range(0, $wanted - 1))
            : [0];

        foreach (array_unique($indexes) as $i) {
            $ticks[] = [
                'point' => $points[$i],
                // The end labels are pinned to the ends instead of centred on their
                // dot, which would hang them half off the card.
                'align' => $last > 0 ? ($i === 0 ? 'lead' : ($i === $last ? 'trail' : '')) : '',
            ];
        }
    }
@endphp

{{-- A plain hand-rolled SVG line — no charting library. Colours and weights from
     design/build: a teal line with hollow readings on it. One colour throughout,
     whatever the numbers do: a reading is a number, never a verdict (rule 4).
     The scale exists so the line can be read, and stops there — no target weight,
     no trend, no comment on the direction.

     The box is stretched to the card's width, so the line spans it at any size.
     That would squash a <circle> into an oval, so each reading is instead a
     zero-length round-capped stroke — a teal dot with a white one inside it —
     whose width is in screen pixels and so survives the stretch round. For the
     same reason the axis labels are HTML beside and below the SVG rather than
     <text> inside it: stretched text is squashed text. --}}
@if (count($points) > 0)
    <div class="chart-frame" style="--chart-h:{{ $height }}px">
        <div class="chart-scale" aria-hidden="true">
            @foreach ($scale as [$value, $y])
                <span style="top:{{ round($y / $boxH * 100, 2) }}%">{{ \App\Support\Format::weight($value) }}</span>
            @endforeach
        </div>

        <div class="chart-plot">
            <svg class="chart" width="100%" height="{{ $height }}" viewBox="{{ $box }}"
                 preserveAspectRatio="none" role="img" aria-label="{{ $label }}">
                @if ($grid)
                    {{-- Rules on the labelled values, so a label always has something to
                         sit against. They carry no threshold — they are there to make a
                         slope readable, nothing more. --}}
                    @foreach ($scale as [, $y])
                        <line class="chart__grid" x1="10" y1="{{ $y }}" x2="{{ $boxW - 10 }}" y2="{{ $y }}" />
                    @endforeach
                @endif
                @if (count($points) > 1)
                    <polyline class="chart__line"
                              points="@foreach ($points as $p){{ $p['x'] }},{{ $p['y'] }} @endforeach" />
                @endif
                @foreach ($points as $p)
                    <path class="chart__dot" d="M{{ $p['x'] }} {{ $p['y'] }}h0">
                        <title>{{ $p['label'] }}</title>
                    </path>
                    <path class="chart__dot-core" d="M{{ $p['x'] }} {{ $p['y'] }}h0" />
                @endforeach
            </svg>

            <div class="chart-dates" aria-hidden="true">
                @foreach ($ticks as $tick)
                    <span class="{{ $tick['align'] }}" style="left:{{ round($tick['point']['x'] / $boxW * 100, 2) }}%">
                        {{ $tick['point']['date']->translatedFormat('j M') }}
                    </span>
                @endforeach
            </div>
        </div>
    </div>
@endif
