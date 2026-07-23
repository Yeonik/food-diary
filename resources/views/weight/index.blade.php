@extends('layouts.app')

@section('title', __('weight.title'))

@section('content')
    <div class="weight">
        <div class="weight-col">
            <x-card>
                <div class="flabel" style="margin-bottom:10px">{{ __('weight.add') }}</div>
                <form method="post" action="{{ route('weight.store') }}">
                    @csrf
                    <div class="two">
                        <x-field type="date" name="recorded_on" :label="__('weight.date')"
                                 :value="old('recorded_on', now()->toDateString())" required />
                        <x-field type="number" name="weight_kg" :label="__('weight.title').', '.__('weight.kg')"
                                 :value="old('weight_kg')" inputmode="decimal" step="0.1" min="1" max="600" required />
                    </div>
                    <x-button type="submit" style="margin-top:16px">{{ __('weight.add') }}</x-button>
                </form>
            </x-card>

            @if ($entries->isNotEmpty())
                <div class="wlog">
                    @foreach ($entries as $entry)
                        <div class="wlog-row">
                            <span class="d">{{ $entry->recorded_on->translatedFormat('j F, l') }}</span>
                            <span class="v">{{ \App\Support\Format::weight($entry->weight_kg) }} {{ __('weight.kg') }}</span>
                            <form method="post" action="{{ route('weight.destroy', $entry) }}"
                                  onsubmit="return confirm('{{ __('weight.confirm_delete') }}')">
                                @csrf @method('DELETE')
                                <x-icon-button type="submit" tone="danger" :label="__('common.delete')" icon="delete" />
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        @if ($entries->isEmpty())
            <div class="weight-chart">
                <x-empty-state icon="weight" :title="__('weight.empty_title')" :body="__('weight.empty_body')" />
            </div>
        @elseif (count($chart) > 0)
            <x-card class="weight-chart">
                <div class="chart-title"><span>{{ __('weight.chart_label') }}</span></div>
                <x-chart.weight-line :points="$chart" :label="__('weight.chart_label')" />
            </x-card>
        @endif
    </div>
@endsection
