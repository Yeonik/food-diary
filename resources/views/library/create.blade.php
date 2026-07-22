@extends('layouts.app')

@section('title', __('library.add_title'))

@section('content')
    <h1>{{ __('library.add_title') }}</h1>
    <p class="muted">{{ __('library.add_subtitle') }}</p>

    <form method="post" action="{{ route('library.store') }}" class="panel">
        @csrf
        <div>
            <label>{{ __('library.field_name') }}</label>
            <input type="text" name="name" value="{{ old('name') }}" style="width:100%" required>
        </div>
        <div style="margin-top:10px">
            <label>{{ __('library.field_alt_name') }} <span class="muted">{{ __('common.optional') }}</span></label>
            <input type="text" name="alt_name" value="{{ old('alt_name') }}" style="width:100%">
            <p class="muted" style="margin:4px 0 0">{{ __('library.alt_name_hint') }}</p>
        </div>
        <div style="margin-top:10px">
            <label>{{ __('library.field_barcode') }} <span class="muted">{{ __('common.optional') }}</span></label>
            <input type="text" name="external_id" value="{{ old('external_id') }}" inputmode="numeric" style="width:100%">
            <p class="muted" style="margin:4px 0 0">{{ __('library.barcode_hint') }}</p>
        </div>
        <div class="row" style="margin-top:10px">
            <div><label>{{ __('library.per_100g_kcal') }}</label><input type="number" step="0.1" name="kcal_per_100g" value="{{ old('kcal_per_100g') }}" required></div>
            <div><label>{{ __('library.per_100g_protein') }}</label><input type="number" step="0.1" name="protein_g_per_100g" value="{{ old('protein_g_per_100g') }}" required></div>
            <div><label>{{ __('library.per_100g_fat') }}</label><input type="number" step="0.1" name="fat_g_per_100g" value="{{ old('fat_g_per_100g') }}" required></div>
            <div><label>{{ __('library.per_100g_carbs') }}</label><input type="number" step="0.1" name="carbs_g_per_100g" value="{{ old('carbs_g_per_100g') }}" required></div>
        </div>
        <p style="margin-top:12px"><button type="submit">{{ __('common.save') }}</button></p>
    </form>
@endsection
