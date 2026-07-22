@extends('layouts.app')

@section('title', __('history.title'))

@section('content')
    @if (! $hasEntries)
        <x-empty-state icon="history" :title="__('history.empty_title')" :body="__('history.empty_body')" />
    @else
        {{-- Period: week / month / a custom range. Links, so it works without JS. --}}
        <x-segmented :label="__('history.title')" class="history__period">
            @foreach (['week', 'month', 'range'] as $r)
                <a class="segmented__btn {{ $range === $r ? 'is-active' : '' }}"
                   href="{{ route('history.index', ['range' => $r]) }}">{{ __('history.'.$r) }}</a>
            @endforeach
        </x-segmented>

        @if ($range === 'range')
            <x-card>
                <form method="get" action="{{ route('history.index') }}">
                    <input type="hidden" name="range" value="range">
                    <div class="field-row">
                        <x-field type="date" name="from" :label="__('history.from')" :value="$from->toDateString()" />
                        <x-field type="date" name="to" :label="__('history.to')" :value="$to->toDateString()" />
                    </div>
                    <x-button type="submit">{{ __('history.apply') }}</x-button>
                </form>
            </x-card>
        @endif

        {{-- Charts side by side on a wide screen, stacked on mobile. --}}
        <div class="history__charts">
            <x-card pad="compact">
                <div class="chart-head">
                    <span class="chart-head__title">{{ __('history.kcal_per_day') }}</span>
                    @if ($goalKcal !== null)
                        <span class="chart-head__meta">— {{ __('history.goal_ref', ['kcal' => \App\Support\Format::kcal($goalKcal)]) }}</span>
                    @endif
                </div>
                <x-chart.kcal-bars :days="$summary->days" :goal="$goalKcal" :label="__('history.kcal_per_day')" />
            </x-card>

            @if (count($weightPoints) > 0)
                <x-card pad="compact">
                    <div class="chart-head">
                        <span class="chart-head__title">{{ __('history.weight_trend') }}</span>
                        @if ($latestWeight !== null)
                            <span class="chart-head__meta">{{ \App\Support\Format::weight($latestWeight) }} {{ __('weight.kg') }}</span>
                        @endif
                    </div>
                    <x-chart.weight-line :points="$weightPoints" :label="__('history.weight_trend')" />
                </x-card>
            @endif
        </div>

        {{-- Summary tiles: average, macro split, entry count. --}}
        <div class="tiles history__tiles">
            <div class="tile">
                <div class="tile__label">{{ __('history.avg_per_day') }}</div>
                <div class="tile__value">{{ \App\Support\Format::kcal($summary->avgKcalPerDay) }}<span class="tile__unit"> {{ __('nutrition.kcal') }}</span></div>
            </div>
            <div class="tile">
                <div class="tile__label">{{ __('history.macro_split') }}</div>
                <div class="tile__chart">
                    <x-chart.macro-split :protein="$summary->proteinG" :fat="$summary->fatG" :carbs="$summary->carbsG" />
                </div>
            </div>
            <div class="tile">
                <div class="tile__label">{{ __('history.entries_count') }}</div>
                <div class="tile__value">{{ $summary->entryCount }}</div>
            </div>
        </div>
    @endif
@endsection
