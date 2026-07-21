@extends('layouts.app')

@section('title', 'Edit entry')

@section('content')
    <h1>Edit entry</h1>
    <p class="muted">This changes only this entry. Editing or deleting is free and unpenalised.</p>

    <form method="post" action="{{ route('entries.update', $entry) }}" class="panel">
        @csrf @method('PATCH')
        <div class="row">
            <div style="flex:1">
                <label>Name</label>
                <input type="text" name="name" value="{{ old('name', $entry->name) }}" style="width:100%">
            </div>
            <div>
                <label>Meal</label>
                <select name="meal">
                    @foreach ($mealTypes as $meal)
                        <option value="{{ $meal->value }}" @selected($entry->meal === $meal)>{{ $meal->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="row" style="margin-top:10px">
            <div><label>Grams</label><input type="number" step="0.1" name="grams" value="{{ old('grams', $entry->grams) }}"></div>
            <div><label>kcal</label><input type="number" step="0.1" name="kcal" value="{{ old('kcal', $entry->kcal) }}"></div>
            <div><label>Protein g</label><input type="number" step="0.1" name="protein_g" value="{{ old('protein_g', $entry->protein_g) }}"></div>
            <div><label>Fat g</label><input type="number" step="0.1" name="fat_g" value="{{ old('fat_g', $entry->fat_g) }}"></div>
            <div><label>Carbs g</label><input type="number" step="0.1" name="carbs_g" value="{{ old('carbs_g', $entry->carbs_g) }}"></div>
        </div>
        <p style="margin-top:12px"><button type="submit">Save</button>
            <a class="btn link" href="{{ route('diary.index', ['date' => $entry->logged_at->toDateString()]) }}">Cancel</a></p>
    </form>
@endsection
