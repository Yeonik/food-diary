@extends('layouts.app')

@section('title', 'Edit item')

@section('content')
    <h1>Correct “{{ $item->name }}”</h1>
    <p class="muted">Corrections apply to future logs. Past entries keep the numbers they were logged with.</p>

    <form method="post" action="{{ route('library.update', $item) }}" class="panel">
        @csrf @method('PATCH')
        <div>
            <label>Name</label>
            <input type="text" name="name" value="{{ old('name', $item->name) }}" style="width:100%" required>
        </div>
        <div style="margin-top:10px">
            <label>Name in another language <span class="muted">(optional)</span></label>
            <input type="text" name="alt_name" value="{{ old('alt_name', $item->alt_name) }}" style="width:100%">
            <p class="muted" style="margin:4px 0 0">A photo can find this item by either name.</p>
        </div>
        <div style="margin-top:10px">
            <label>Barcode <span class="muted">(optional)</span></label>
            <input type="text" name="external_id" value="{{ old('external_id', $item->external_id) }}" inputmode="numeric" style="width:100%">
            <p class="muted" style="margin:4px 0 0">The stable id that makes this product match exactly — no camera needed.</p>
        </div>
        <div class="row" style="margin-top:10px">
            <div><label>kcal / 100 g</label><input type="number" step="0.1" name="kcal_per_100g" value="{{ old('kcal_per_100g', $item->kcal_per_100g) }}" required></div>
            <div><label>Protein g / 100 g</label><input type="number" step="0.1" name="protein_g_per_100g" value="{{ old('protein_g_per_100g', $item->protein_g_per_100g) }}" required></div>
            <div><label>Fat g / 100 g</label><input type="number" step="0.1" name="fat_g_per_100g" value="{{ old('fat_g_per_100g', $item->fat_g_per_100g) }}" required></div>
            <div><label>Carbs g / 100 g</label><input type="number" step="0.1" name="carbs_g_per_100g" value="{{ old('carbs_g_per_100g', $item->carbs_g_per_100g) }}" required></div>
        </div>
        <p style="margin-top:12px"><button type="submit">Save</button>
            <a class="btn link" href="{{ route('library.index') }}">Cancel</a></p>
    </form>
@endsection
