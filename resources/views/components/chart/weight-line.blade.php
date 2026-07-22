@props(['points' => [], 'label' => ''])

{{-- A plain hand-rolled SVG line. No charting library — the points arrive as
     pre-computed coordinates in a 600×200 box. --}}
@if (count($points) > 0)
    <svg class="chart" viewBox="0 0 600 200" role="img" aria-label="{{ $label }}">
        @if (count($points) > 1)
            <polyline class="chart__line"
                      points="@foreach ($points as $p){{ $p['x'] }},{{ $p['y'] }} @endforeach" />
        @endif
        @foreach ($points as $p)
            <circle class="chart__dot" cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="3">
                <title>{{ $p['label'] }}</title>
            </circle>
        @endforeach
    </svg>
@endif
