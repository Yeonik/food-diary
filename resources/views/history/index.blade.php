@extends('layouts.app')

@section('title', __('history.title'))

@section('content')
    <h1>{{ __('history.title') }}</h1>

    <div class="chips">
        <a class="chip {{ $range === 'week' ? 'chip--active' : '' }}"
           href="{{ route('history.index', ['range' => 'week']) }}">{{ __('history.week') }}</a>
        <a class="chip {{ $range === 'month' ? 'chip--active' : '' }}"
           href="{{ route('history.index', ['range' => 'month']) }}">{{ __('history.month') }}</a>
        {{-- The custom date range is wired with the charts pass. --}}
        <a class="chip" href="{{ route('history.index', ['range' => 'range']) }}">{{ __('history.range') }}</a>
    </div>

    @if (! $hasEntries)
        <div class="empty">
            <x-icon name="history" class="empty__icon" />
            <div class="empty__title">{{ __('history.empty_title') }}</div>
            <p class="empty__body">{{ __('history.empty_body') }}</p>
        </div>
    @else
        {{-- The kcal-per-day bars and the weight line render here with the charts pass. --}}
        <div class="tiles">
            <div class="tile">
                <div class="tile__label">{{ __('history.avg_per_day') }}</div>
                <div class="tile__value">{{ \App\Support\Format::kcal($avgKcalPerDay) }} <span class="caption">{{ __('nutrition.kcal') }}</span></div>
            </div>
            <div class="tile">
                <div class="tile__label">{{ __('history.entries_count') }}</div>
                <div class="tile__value">{{ $entryCount }}</div>
            </div>
        </div>

        <div class="card">
            <div class="section-title"><h2>{{ __('history.macro_split') }}</h2></div>
            <x-chart.macro-split :protein="$protein" :fat="$fat" :carbs="$carbs" />
        </div>
    @endif
@endsection
