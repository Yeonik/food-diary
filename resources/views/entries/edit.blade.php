@extends('layouts.app')

@section('title', __('entries.title'))

@section('content')
    <h1>{{ __('entries.title') }}</h1>
    <p class="muted">{{ __('entries.note') }}</p>

    <form method="post" action="{{ route('entries.update', $entry) }}" class="panel">
        @csrf @method('PATCH')
        <div class="row">
            <div style="flex:1">
                <label>{{ __('entries.name') }}</label>
                <input type="text" name="name" value="{{ old('name', $entry->name) }}" style="width:100%">
            </div>
            <div>
                <label>{{ __('entries.meal') }}</label>
                <select name="meal">
                    @foreach ($mealTypes as $meal)
                        <option value="{{ $meal->value }}" @selected($entry->meal === $meal)>{{ __('meal.'.$meal->value) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="row" style="margin-top:10px">
            <div><label>{{ __('entries.grams') }}</label><input type="number" step="0.1" name="grams" value="{{ old('grams', $entry->grams) }}"></div>
            <div><label>{{ __('nutrition.kcal') }}</label><input type="number" step="0.1" name="kcal" value="{{ old('kcal', $entry->kcal) }}"></div>
            <div><label>{{ __('nutrition.protein') }} {{ __('nutrition.g') }}</label><input type="number" step="0.1" name="protein_g" value="{{ old('protein_g', $entry->protein_g) }}"></div>
            <div><label>{{ __('nutrition.fat') }} {{ __('nutrition.g') }}</label><input type="number" step="0.1" name="fat_g" value="{{ old('fat_g', $entry->fat_g) }}"></div>
            <div><label>{{ __('nutrition.carbs') }} {{ __('nutrition.g') }}</label><input type="number" step="0.1" name="carbs_g" value="{{ old('carbs_g', $entry->carbs_g) }}"></div>
        </div>
        <p style="margin-top:12px"><button type="submit">{{ __('common.save') }}</button>
            <a class="btn link" href="{{ route('diary.index', ['date' => $entry->logged_at->toDateString()]) }}">{{ __('common.cancel') }}</a></p>
    </form>
@endsection
