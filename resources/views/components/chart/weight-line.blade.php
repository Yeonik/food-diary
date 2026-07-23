@props([
    'points' => [],
    'label' => '',
    'box' => '0 0 900 200',   // must match the box WeightSeries laid the points out in
    'height' => 150,
    'grid' => false,          // faint horizontal rules, for the tall Weight-screen line
])

@php
    // The box's own width and height, so the grid divides whatever box it is given.
    [, , $boxW, $boxH] = array_map(floatval(...), explode(' ', $box) + [0, 0, 900, 200]);
@endphp

{{-- A plain hand-rolled SVG line — no charting library. Colours and weights from
     design/build: a teal line with hollow readings on it. One colour throughout,
     whatever the numbers do: a reading is a number, never a verdict (rule 4).

     The box is stretched to the card's width, so the line spans it at any size.
     That would squash a <circle> into an oval, so each reading is instead a
     zero-length round-capped stroke — a teal dot with a white one inside it —
     whose width is in screen pixels and so survives the stretch round. --}}
@if (count($points) > 0)
    <svg class="chart" width="100%" height="{{ $height }}" viewBox="{{ $box }}"
         preserveAspectRatio="none" role="img" aria-label="{{ $label }}">
        @if ($grid)
            {{-- Three rules at the build's divisions. They carry no values and no
                 thresholds — they are there to make a slope readable, nothing more. --}}
            @foreach ([0.125, 0.4375, 0.75] as $at)
                <line class="chart__grid" x1="10" y1="{{ round($boxH * $at, 1) }}" x2="{{ $boxW - 10 }}" y2="{{ round($boxH * $at, 1) }}" />
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
@endif
