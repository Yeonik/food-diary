@extends('layouts.app')

@section('title', 'Add an item')

@section('content')
    <h1>Add a library item</h1>
    <p class="muted">A direct nutrient profile, per 100 g.</p>

    <form method="post" action="{{ route('library.store') }}" class="panel">
        @csrf
        <div>
            <label>Name</label>
            <input type="text" name="name" value="{{ old('name') }}" style="width:100%" required>
        </div>
        <div class="row" style="margin-top:10px">
            <div><label>kcal / 100 g</label><input type="number" step="0.1" name="kcal_per_100g" value="{{ old('kcal_per_100g') }}" required></div>
            <div><label>Protein g / 100 g</label><input type="number" step="0.1" name="protein_g_per_100g" value="{{ old('protein_g_per_100g') }}" required></div>
            <div><label>Fat g / 100 g</label><input type="number" step="0.1" name="fat_g_per_100g" value="{{ old('fat_g_per_100g') }}" required></div>
            <div><label>Carbs g / 100 g</label><input type="number" step="0.1" name="carbs_g_per_100g" value="{{ old('carbs_g_per_100g') }}" required></div>
        </div>
        <p style="margin-top:12px"><button type="submit">Save</button></p>
    </form>
@endsection
