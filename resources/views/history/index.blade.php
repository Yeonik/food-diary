@extends('layouts.app')

@section('title', __('history.title'))

@section('content')
    @if (! $hasEntries)
        <x-empty-state icon="history" :title="__('history.empty_title')" :body="__('history.empty_body')" />
    @else
        {{-- Period: week / month / a custom range. Links, so it works without JS. --}}
        <x-segmented :label="__('history.title')">
            @foreach (['week', 'month', 'range'] as $r)
                <a class="{{ $range === $r ? 'on' : '' }}"
                   href="{{ route('history.index', ['range' => $r]) }}">{{ __('history.'.$r) }}</a>
            @endforeach
        </x-segmented>

        @if ($range === 'range')
            <x-card style="margin-bottom:16px">
                <form method="get" action="{{ route('history.index') }}">
                    <input type="hidden" name="range" value="range">
                    <div class="two">
                        <x-field type="date" name="from" :label="__('history.from')" :value="$from->toDateString()" />
                        <x-field type="date" name="to" :label="__('history.to')" :value="$to->toDateString()" />
                    </div>
                    <x-button type="submit" style="margin-top:16px">{{ __('history.apply') }}</x-button>
                </form>
            </x-card>
        @endif

        {{-- Charts side by side on a wide screen, stacked on mobile. --}}
        <div class="grid-auto">
            <x-card>
                <div class="chart-title">
                    <span>{{ __('history.kcal_per_day') }}</span>
                    @if ($goalKcal !== null)
                        <span>{{ __('history.goal_ref', ['kcal' => \App\Support\Format::kcal($goalKcal)]) }}</span>
                    @endif
                </div>
                <x-chart.kcal-bars :days="$summary->days" :goal="$goalKcal" :label="__('history.kcal_per_day')" />
            </x-card>

            @if (count($weightPoints) > 0)
                <x-card>
                    <div class="chart-title">
                        <span>{{ __('history.weight_trend') }}</span>
                        @if ($latestWeight !== null)
                            <span>{{ \App\Support\Format::weight($latestWeight) }} {{ __('weight.kg') }}</span>
                        @endif
                    </div>
                    <x-chart.weight-line :points="$weightPoints" :label="__('history.weight_trend')" />
                </x-card>
            @endif
        </div>

        {{-- Summary tiles: average, macro split, entry count. --}}
        <div class="grid-stats">
            <div class="stat">
                <div class="l">{{ __('history.avg_per_day') }}</div>
                <div class="v">{{ \App\Support\Format::kcal($summary->avgKcalPerDay) }}<small> {{ __('nutrition.kcal') }}</small></div>
            </div>
            <div class="stat">
                <div class="l" style="margin-bottom:10px">{{ __('history.macro_split') }}</div>
                <x-chart.macro-split :protein="$summary->proteinG" :fat="$summary->fatG" :carbs="$summary->carbsG" />
            </div>
            <div class="stat">
                <div class="l">{{ __('history.entries_count') }}</div>
                <div class="v">{{ $summary->entryCount }}</div>
            </div>
        </div>
    @endif
@endsection
