@extends('layouts.app')

@section('title', __('weight.title'))

@section('content')
    <h1>{{ __('weight.title') }}</h1>
    <p class="muted">{{ __('weight.subtitle') }}</p>

    <form method="post" action="{{ route('weight.store') }}" class="card">
        @csrf
        <div class="field-row">
            <div class="field">
                <label for="recorded_on">{{ __('weight.date') }}</label>
                <input type="date" id="recorded_on" name="recorded_on"
                       value="{{ old('recorded_on', now()->toDateString()) }}" required>
            </div>
            <div class="field">
                <label for="weight_kg">{{ __('weight.title') }}, {{ __('weight.kg') }}</label>
                <input type="number" id="weight_kg" name="weight_kg" inputmode="decimal"
                       step="0.1" min="1" max="600" value="{{ old('weight_kg') }}" required>
            </div>
        </div>
        <button class="btn" type="submit">{{ __('weight.add') }}</button>
    </form>

    @if ($entries->isEmpty())
        <div class="empty">
            <x-icon name="weight" class="empty__icon" />
            <div class="empty__title">{{ __('weight.empty_title') }}</div>
            <p class="empty__body">{{ __('weight.empty_body') }}</p>
        </div>
    @else
        @if (count($chart) > 0)
            <div class="card">
                <x-chart.weight-line :points="$chart" :label="__('weight.chart_label')" />
            </div>
        @endif

        <ul class="list">
            @foreach ($entries as $entry)
                <li class="list__row">
                    <div class="list__body">
                        <div class="list__title">{{ \App\Support\Format::weight($entry->weight_kg) }} {{ __('weight.kg') }}</div>
                        <div class="caption">{{ $entry->recorded_on->translatedFormat('j F, l') }}</div>
                    </div>
                    <form method="post" action="{{ route('weight.destroy', $entry) }}"
                          onsubmit="return confirm('{{ __('weight.confirm_delete') }}')">
                        @csrf @method('DELETE')
                        <button class="btn btn--danger-quiet" type="submit">{{ __('common.delete') }}</button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
