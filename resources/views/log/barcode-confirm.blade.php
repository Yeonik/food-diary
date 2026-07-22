@extends('layouts.app')

@section('title', __('barcode.confirm_title'))

@section('content')
    <h1>{{ __('barcode.confirm_title') }}</h1>
    <p class="muted">{{ __('barcode.confirm_intro') }}</p>

    @php $c = $product['candidate']; @endphp

    {{-- One product, resolved from the code. No thumbnail here by design: the
         library and its neighbours stay free of third-party image requests; the
         picture is only shown on the photo confirm screen. --}}
    <form method="post" action="{{ route('log.barcode.confirm.store') }}" class="card confirm-dish">
        @csrf
        <div class="confirm-dish__head">
            <span class="confirm-dish__name">{{ $product['name'] }}</span>
        </div>

        <div class="source__body">
            <x-prov :source="\App\Nutrition\NutrientSource::from($c['source'])" />
            <span class="caption">
                {{ \App\Support\Format::kcal($c['kcal']) }} {{ __('nutrition.kcal') }} ·
                {{ \App\Support\Format::macro($c['protein']) }}{{ __('nutrition.p') }} /
                {{ \App\Support\Format::macro($c['fat']) }}{{ __('nutrition.f') }} /
                {{ \App\Support\Format::macro($c['carbs']) }}{{ __('nutrition.c') }} ·
                {{ __('nutrition.per_100g') }}
            </span>
        </div>

        <div class="field-row">
            <div class="field">
                <label for="meal">{{ __('confirm.meal') }}</label>
                <select name="meal" id="meal">
                    @foreach ($mealTypes as $meal)
                        <option value="{{ $meal->value }}">{{ __('meal.'.$meal->value) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="date">{{ __('confirm.date') }}</label>
                <input type="date" name="date" id="date" value="{{ now()->toDateString() }}">
            </div>
        </div>

        <div class="field">
            <label for="grams">{{ __('confirm.grams') }}</label>
            <input type="number" id="grams" name="grams" inputmode="decimal" step="0.1" min="0.1" max="5000"
                   value="{{ old('grams', $product['grams']) }}">
        </div>

        @foreach (['meal', 'date', 'grams'] as $field)
            @error($field)<p class="field-error">{{ $message }}</p>@enderror
        @endforeach

        <button class="btn btn--block" type="submit">{{ __('barcode.log') }}</button>
    </form>
@endsection
