@extends('layouts.app')

@section('title', __('library.edit_title', ['name' => $item->name]))

@section('content')
    <div class="narrow560 vform">
        <p class="caption">{{ __('library.edit_subtitle') }}</p>

        <form method="post" action="{{ route('library.update', $item) }}" class="vform">
            @csrf @method('PATCH')
            <x-field name="name" :label="__('library.field_name')" :value="old('name', $item->name)" required />
            <x-field name="alt_name" :value="old('alt_name', $item->alt_name)" :hint="__('library.alt_name_hint_edit')"
                     :label="__('library.field_alt_name').' '.__('common.optional')" />
            <x-field name="external_id" :value="old('external_id', $item->external_id)" inputmode="numeric"
                     :label="__('library.field_barcode').' '.__('common.optional')" :hint="__('library.barcode_hint_edit')" />

            <x-card style="border-radius:16px;padding:16px">
                <div class="flabel" style="margin-bottom:10px">{{ __('nutrition.per_100g') }}</div>
                <div class="nutri-inputs">
                    @foreach ([
                        'kcal_per_100g' => 'library.per_100g_kcal',
                        'protein_g_per_100g' => 'library.per_100g_protein',
                        'fat_g_per_100g' => 'library.per_100g_fat',
                        'carbs_g_per_100g' => 'library.per_100g_carbs',
                    ] as $field => $key)
                        <div>
                            <label class="flabel" for="{{ $field }}" style="font-size:11px;margin-bottom:6px">{{ __($key) }}</label>
                            <input type="number" class="field" step="0.1" id="{{ $field }}" name="{{ $field }}"
                                   value="{{ old($field, $item->{$field}) }}" required>
                        </div>
                    @endforeach
                </div>
            </x-card>

            <div class="actions-end" style="margin-top:0">
                <x-button variant="secondary" href="{{ route('library.index') }}">{{ __('common.cancel') }}</x-button>
                <x-button type="submit">{{ __('common.save') }}</x-button>
            </div>
        </form>
    </div>
@endsection
