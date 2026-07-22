@extends('layouts.app')

@section('title', __('history.title'))

@section('content')
    <h1>{{ __('history.title') }}</h1>

    <div class="chips">
        <a class="chip {{ $range === 'week' ? 'chip--active' : '' }}"
           href="{{ route('history.index', ['range' => 'week']) }}">{{ __('history.week') }}</a>
        <a class="chip {{ $range === 'month' ? 'chip--active' : '' }}"
           href="{{ route('history.index', ['range' => 'month']) }}">{{ __('history.month') }}</a>
        <a class="chip {{ $range === 'range' ? 'chip--active' : '' }}"
           href="{{ route('history.index', ['range' => 'range']) }}">{{ __('history.range') }}</a>
    </div>

    @if ($range === 'range')
        <form method="get" action="{{ route('history.index') }}" class="card">
            <input type="hidden" name="range" value="range">
            <div class="field-row">
                <div class="field">
                    <label for="from">{{ __('history.from') }}</label>
                    <input type="date" id="from" name="from" value="{{ $from->toDateString() }}">
                </div>
                <div class="field">
                    <label for="to">{{ __('history.to') }}</label>
                    <input type="date" id="to" name="to" value="{{ $to->toDateString() }}">
                </div>
            </div>
            <button class="btn" type="submit">{{ __('history.apply') }}</button>
        </form>
    @endif

    @if (! $hasEntries)
        <div class="empty">
            <x-icon name="history" class="empty__icon" />
            <div class="empty__title">{{ __('history.empty_title') }}</div>
            <p class="empty__body">{{ __('history.empty_body') }}</p>
        </div>
    @else
        <div class="card">
            <div class="section-title"><h2>{{ __('history.kcal_per_day') }}</h2></div>
            <x-chart.kcal-bars :days="$summary->days" :goal="$goalKcal" :label="__('history.kcal_per_day')" />
        </div>

        @if (count($weightPoints) > 0)
            <div class="card">
                <div class="section-title"><h2>{{ __('history.weight_trend') }}</h2></div>
                <x-chart.weight-line :points="$weightPoints" :label="__('history.weight_trend')" />
            </div>
        @endif

        <div class="tiles">
            <div class="tile">
                <div class="tile__label">{{ __('history.avg_per_day') }}</div>
                <div class="tile__value">{{ \App\Support\Format::kcal($summary->avgKcalPerDay) }} <span class="caption">{{ __('nutrition.kcal') }}</span></div>
            </div>
            <div class="tile">
                <div class="tile__label">{{ __('history.entries_count') }}</div>
                <div class="tile__value">{{ $summary->entryCount }}</div>
            </div>
        </div>

        <div class="card">
            <div class="section-title"><h2>{{ __('history.macro_split') }}</h2></div>
            <x-chart.macro-split :protein="$summary->proteinG" :fat="$summary->fatG" :carbs="$summary->carbsG" />
        </div>
    @endif
@endsection
