@extends('layouts.app')

@section('title', __('barcode.confirm_title'))

@section('content')
    @php $c = $product['candidate']; @endphp

    <div class="narrow560">
        <p class="caption" style="margin-bottom:16px">{{ __('barcode.confirm_intro') }}</p>

        <form method="post" action="{{ route('log.barcode.confirm.store') }}">
            @csrf
            <x-card style="padding:22px">
                <div style="display:flex;gap:16px;align-items:center">
                    {{-- Open Food Facts thumbnail, pulled by link and removed on failure or
                         when absent. This is the same moment as the code lookup — a request
                         for this product just went out — so the picture leaks nothing new. --}}
                    @if (! empty($c['image_url']))
                        <div class="cthumb" style="width:96px;height:96px;border-radius:16px">
                            <img src="{{ $c['image_url'] }}" alt="" loading="lazy" onerror="this.parentNode.remove()">
                        </div>
                    @endif
                    <div style="flex:1;min-width:0">
                        <div style="font-size:17px;font-weight:800">{{ $product['name'] }}</div>
                        <div style="margin-top:8px">
                            <x-source-chip :source="\App\Nutrition\NutrientSource::from($c['source'])" />
                        </div>
                    </div>
                </div>

                <div class="nutri-grid">
                    <div class="nutri"><div class="l">{{ __('nutrition.kcal') }}</div><div class="v">{{ \App\Support\Format::kcal($c['kcal']) }}</div></div>
                    <div class="nutri"><div class="l">{{ __('nutrition.protein') }}</div><div class="v">{{ \App\Support\Format::macro($c['protein']) }}</div></div>
                    <div class="nutri"><div class="l">{{ __('nutrition.fat') }}</div><div class="v">{{ \App\Support\Format::macro($c['fat']) }}</div></div>
                    <div class="nutri"><div class="l">{{ __('nutrition.carbs') }}</div><div class="v">{{ \App\Support\Format::macro($c['carbs']) }}</div></div>
                </div>
                <div style="font-size:11px;color:var(--text-muted);font-weight:600;text-align:center;margin-top:8px">{{ __('nutrition.per_100g') }}</div>

                <div class="two" style="margin-top:18px">
                    <div>
                        <label class="flabel" for="grams">{{ __('confirm.grams') }}</label>
                        <input type="number" class="field" id="grams" name="grams" inputmode="decimal"
                               step="0.1" min="0.1" max="5000" value="{{ old('grams', $product['grams']) }}">
                    </div>
                    <div>
                        <label class="flabel" for="meal">{{ __('confirm.meal') }}</label>
                        <select name="meal" id="meal" class="field">
                            @foreach ($mealTypes as $meal)
                                <option value="{{ $meal->value }}">{{ __('meal.'.$meal->value) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="flabel" for="date">{{ __('confirm.date') }}</label>
                        <input type="date" class="field" name="date" id="date" value="{{ now()->toDateString() }}">
                    </div>
                </div>

                @foreach (['meal', 'date', 'grams'] as $field)
                    @error($field)<p class="field-error">{{ $message }}</p>@enderror
                @endforeach
            </x-card>

            <div class="actions-end">
                <x-button variant="secondary" href="{{ route('log.barcode') }}">{{ __('common.cancel') }}</x-button>
                <x-button type="submit">{{ __('barcode.log') }}</x-button>
            </div>
        </form>
    </div>
@endsection
