@extends('layouts.app')

@section('title', 'Goal')

@section('content')
    <h1>Daily goal</h1>
    <p class="muted">
        Entirely optional. Leave a field blank to track it without a target. The
        diary works with no goal set at all — it just won't show a "remaining".
    </p>

    <form method="post" action="{{ route('goal.update') }}" class="panel">
        @csrf @method('PATCH')
        <div class="row">
            <div><label>Daily kcal</label><input type="number" step="1" min="0" name="daily_kcal" value="{{ old('daily_kcal', $goal?->daily_kcal) }}"></div>
            <div><label>Protein g</label><input type="number" step="0.1" min="0" name="protein_g" value="{{ old('protein_g', $goal?->protein_g) }}"></div>
            <div><label>Fat g</label><input type="number" step="0.1" min="0" name="fat_g" value="{{ old('fat_g', $goal?->fat_g) }}"></div>
            <div><label>Carbs g</label><input type="number" step="0.1" min="0" name="carbs_g" value="{{ old('carbs_g', $goal?->carbs_g) }}"></div>
        </div>
        <p style="margin-top:12px"><button type="submit">Save goal</button></p>
    </form>
@endsection
