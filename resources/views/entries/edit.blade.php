@extends('layouts.app')

@section('title', __('entries.title'))

@section('content')
    <div class="narrow520 vform">
        {{-- The source this entry was logged with. It is a snapshot: correcting
             the library item it came from never reaches back and changes it. --}}
        <div style="display:flex;justify-content:flex-end">
            <x-source-chip :source="$entry->source" />
        </div>

        <p class="caption">{{ __('entries.note') }}</p>

        <form method="post" action="{{ route('entries.update', $entry) }}" class="vform" id="entry-form">
            @csrf @method('PATCH')

            <x-field name="name" :label="__('entries.name')" :value="old('name', $entry->name)" />

            <div class="two">
                <div>
                    <label class="flabel" for="grams">{{ __('entries.grams') }}</label>
                    <input type="number" class="field" step="0.1" min="0" id="grams" name="grams"
                           inputmode="decimal" value="{{ old('grams', $entry->grams) }}">
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

            {{-- The numbers as logged, in this portion — editable, because a
                 correction to what was eaten belongs to the person who ate it. --}}
            <div>
                <div class="flabel" style="margin-bottom:10px">{{ __('entries.in_this_portion') }}</div>
                <div class="nutri-inputs">
                    @foreach ([
                        'kcal' => 'nutrition.kcal',
                        'protein_g' => 'nutrition.protein',
                        'fat_g' => 'nutrition.fat',
                        'carbs_g' => 'nutrition.carbs',
                    ] as $field => $key)
                        <div>
                            <label class="flabel" for="{{ $field }}" style="font-size:11px;margin-bottom:6px">{{ __($key) }}</label>
                            <input type="number" class="field" step="0.1" min="0" id="{{ $field }}" name="{{ $field }}"
                                   inputmode="decimal" value="{{ old($field, $entry->{$field}) }}">
                        </div>
                    @endforeach
                </div>
            </div>

            @foreach (['name', 'meal', 'grams', 'kcal', 'protein_g', 'fat_g', 'carbs_g'] as $field)
                @error($field)<p class="field-error">{{ $message }}</p>@enderror
            @endforeach
        </form>

        {{-- Deleting is as available as saving, and costs nothing: no warning
             beyond the confirm, no tally, no penalty. --}}
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
            <form method="post" action="{{ route('entries.destroy', $entry) }}"
                  onsubmit="return confirm('{{ __('common.confirm_delete') }}')">
                @csrf @method('DELETE')
                <x-button type="submit" variant="danger" icon="delete">{{ __('common.delete') }}</x-button>
            </form>

            <div style="display:flex;gap:10px">
                <x-button variant="secondary" href="{{ route('diary.index', ['date' => $entry->logged_at->toDateString()]) }}">{{ __('common.cancel') }}</x-button>
                <x-button type="submit" form="entry-form">{{ __('common.save') }}</x-button>
            </div>
        </div>
    </div>
@endsection
