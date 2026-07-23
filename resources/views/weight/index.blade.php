@extends('layouts.app')

@section('title', __('weight.title'))

@section('content')
    {{-- A log and a line. No BMI, no target weight, no reading of the trend — a
         weight is a number, and the app has no opinion about it (hard rule 4). --}}
    <div class="weight">
        <div class="weight-col">
            <x-card>
                <div class="flabel" style="margin-bottom:10px">{{ __('weight.new_entry') }}</div>

                <form method="post" action="{{ route('weight.store') }}">
                    @csrf
                    <div class="wnew-row">
                        {{-- Text, not number: a phone keyboard set to decimal offers the
                             comma on a Russian layout, and type=number would refuse the
                             value it produces. The controller takes either separator. --}}
                        <label class="wnew-val">
                            <span class="visually-hidden">{{ __('weight.title') }}, {{ __('weight.kg') }}</span>
                            <input type="text" name="weight_kg" inputmode="decimal" autocomplete="off"
                                   value="{{ old('weight_kg') }}" required>
                            <span>{{ __('weight.kg') }}</span>
                        </label>
                        <x-button type="submit">{{ __('weight.add') }}</x-button>
                    </div>
                    <span class="fhint">{{ __('weight.decimal_hint') }}</span>

                    <div style="margin-top:14px">
                        <label class="flabel" for="recorded_on">{{ __('weight.date') }}</label>
                        <input type="date" class="field" id="recorded_on" name="recorded_on"
                               value="{{ old('recorded_on', now()->toDateString()) }}" required>
                    </div>

                    @foreach (['weight_kg', 'recorded_on'] as $field)
                        @error($field)<p class="field-error">{{ $message }}</p>@enderror
                    @endforeach
                </form>
            </x-card>

            @if ($entries->isNotEmpty())
                <div class="wlog">
                    @foreach ($entries as $entry)
                        <div class="wlog-row">
                            <span class="d">{{ $entry->recorded_on->translatedFormat('j F, l') }}</span>
                            <span class="wlog-act">
                                <span class="v">{{ \App\Support\Format::weight($entry->weight_kg) }} {{ __('weight.kg') }}</span>
                                <form method="post" action="{{ route('weight.destroy', $entry) }}"
                                      onsubmit="return confirm('{{ __('weight.confirm_delete') }}')">
                                    @csrf @method('DELETE')
                                    <x-icon-button type="submit" tone="danger" :label="__('common.delete')" icon="delete" />
                                </form>
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        @if ($entries->isEmpty())
            <div class="empty" style="flex:1">
                <div class="empty-tile"><x-icon name="weight" /></div>
                <h3>{{ __('weight.empty_title') }}</h3>
                <p>{{ __('weight.empty_body') }}</p>
            </div>
        @else
            <x-card class="weight-chart">
                <div class="chart-title"><span>{{ __('weight.chart_label') }}</span></div>
                {{-- The same line as History draws, at this screen's size. --}}
                <x-chart.weight-line :points="$chart" box="0 0 820 320" height="300" grid
                                     :label="__('weight.chart_label')" />
            </x-card>
        @endif
    </div>
@endsection
