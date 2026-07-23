@extends('layouts.app')

@section('title', __('entries.title'))

@section('content')
    <div class="narrow520 vform">
        <p class="caption">{{ __('entries.note') }}</p>

        <form method="post" action="{{ route('entries.update', $entry) }}" class="vform">
            @csrf @method('PATCH')

            <x-field name="name" :label="__('entries.name')" :value="old('name', $entry->name)" />

            <div class="two">
                <div>
                    <label class="flabel" for="grams">{{ __('entries.grams') }}</label>
                    <input type="number" class="field" step="0.1" id="grams" name="grams" value="{{ old('grams', $entry->grams) }}">
                </div>
                <div>
                    <label class="flabel" for="meal">{{ __('entries.meal') }}</label>
                    <select name="meal" id="meal" class="field">
                        @foreach ($mealTypes as $meal)
                            <option value="{{ $meal->value }}" @selected($entry->meal === $meal)>{{ __('meal.'.$meal->value) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="two">
                <div>
                    <label class="flabel" for="kcal">{{ __('nutrition.kcal') }}</label>
                    <input type="number" class="field" step="0.1" id="kcal" name="kcal" value="{{ old('kcal', $entry->kcal) }}">
                </div>
                <div>
                    <label class="flabel" for="protein_g">{{ __('nutrition.protein') }}, {{ __('nutrition.g') }}</label>
                    <input type="number" class="field" step="0.1" id="protein_g" name="protein_g" value="{{ old('protein_g', $entry->protein_g) }}">
                </div>
                <div>
                    <label class="flabel" for="fat_g">{{ __('nutrition.fat') }}, {{ __('nutrition.g') }}</label>
                    <input type="number" class="field" step="0.1" id="fat_g" name="fat_g" value="{{ old('fat_g', $entry->fat_g) }}">
                </div>
                <div>
                    <label class="flabel" for="carbs_g">{{ __('nutrition.carbs') }}, {{ __('nutrition.g') }}</label>
                    <input type="number" class="field" step="0.1" id="carbs_g" name="carbs_g" value="{{ old('carbs_g', $entry->carbs_g) }}">
                </div>
            </div>

            <div class="actions-end" style="margin-top:0">
                <x-button variant="secondary" href="{{ route('diary.index', ['date' => $entry->logged_at->toDateString()]) }}">{{ __('common.cancel') }}</x-button>
                <x-button type="submit">{{ __('common.save') }}</x-button>
            </div>
        </form>
    </div>
@endsection
