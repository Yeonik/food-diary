@extends('layouts.app')

@section('title', __('confirm.title'))

@section('content')
    <h1>{{ __('confirm.title') }}</h1>
    <p class="muted">{{ __('confirm.intro') }}</p>

    <form method="post" action="{{ route('log.confirm.store') }}">
        @csrf

        <div class="card">
            <div class="field-row">
                <div class="field">
                    <label for="meal">{{ __('confirm.meal') }}</label>
                    <select name="meal" id="meal">
                        @foreach ($mealTypes as $meal)
                            <option value="{{ $meal->value }}">{{ __('meal.'.$meal->value) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="date">{{ __('confirm.date') }}</label>
                    <input type="date" name="date" id="date" value="{{ now()->toDateString() }}">
                </div>
            </div>
        </div>

        @foreach ($items as $i => $item)
            @php $hasVerified = collect($item['candidates'])->contains(fn ($c): bool => $c['verified']); @endphp
            <div class="card confirm-dish">
                <div class="confirm-dish__head">
                    <span class="confirm-dish__name">{{ $item['name'] }}</span>
                    <div class="field confirm-dish__portion">
                        <label for="grams-{{ $i }}">{{ __('confirm.grams') }}</label>
                        <input type="number" id="grams-{{ $i }}" name="items[{{ $i }}][grams]"
                               inputmode="decimal" step="0.1" min="0.1" max="5000" value="{{ $item['grams'] }}">
                    </div>
                </div>

                @unless ($hasVerified)
                    <p class="caption">{{ __('confirm.no_matches') }}</p>
                @endunless

                <div class="sources">
                    {{-- Nothing is selected by default — not USDA, not Open Food Facts,
                         not the top-ranked match. The user chooses per dish. --}}
                    @foreach ($item['candidates'] as $c => $candidate)
                        <label class="source">
                            <input type="radio" name="items[{{ $i }}][candidate]" value="{{ $c }}" data-confirm-source>
                            <span class="source__body">
                                {{-- Open Food Facts thumbnail, pulled by link (never copied
                                     here) and shown only on this screen, where a request for
                                     this product is already in flight. Absent link or a load
                                     failure just removes it — no broken-image placeholder. --}}
                                @if (! empty($candidate['image_url']))
                                    <img class="source__thumb" src="{{ $candidate['image_url'] }}" alt=""
                                         loading="lazy" onerror="this.remove()">
                                @endif
                                <span class="prov {{ $candidate['verified'] ? 'prov--verified' : 'prov--estimate' }}">
                                    <span class="prov__glyph" aria-hidden="true">{{ $candidate['verified'] ? '✓' : '≈' }}</span>
                                    {{ __('source.'.$candidate['source']) }}
                                </span>
                                <strong class="source__name">{{ $candidate['label'] }}</strong>
                                @if (! empty($candidate['matched_via']))
                                    <span class="caption">{{ __('confirm.matched_on', ['term' => $candidate['matched_via']]) }}</span>
                                @endif
                                <span class="caption">
                                    {{ \App\Support\Format::kcal($candidate['kcal']) }} {{ __('nutrition.kcal') }} ·
                                    {{ \App\Support\Format::macro($candidate['protein']) }}{{ __('nutrition.p') }} /
                                    {{ \App\Support\Format::macro($candidate['fat']) }}{{ __('nutrition.f') }} /
                                    {{ \App\Support\Format::macro($candidate['carbs']) }}{{ __('nutrition.c') }} ·
                                    {{ __('nutrition.per_100g') }}
                                </span>
                            </span>
                        </label>
                    @endforeach

                    {{-- Hand-entered label values — always available, verified. --}}
                    <label class="source">
                        <input type="radio" name="items[{{ $i }}][candidate]" value="manual" data-confirm-source>
                        <span class="source__body">
                            <span class="prov prov--verified">
                                <span class="prov__glyph" aria-hidden="true">✓</span> {{ __('confirm.manual_option') }}
                            </span>
                            <span class="caption">{{ __('confirm.manual_hint') }}</span>
                        </span>
                    </label>
                    <div class="manual-fields">
                        <div class="field-row">
                            <div class="field">
                                <label>{{ __('confirm.name') }}</label>
                                <input type="text" name="items[{{ $i }}][manual][name]" value="{{ old("items.$i.manual.name", $item['name']) }}">
                            </div>
                            <div class="field">
                                <label>{{ __('nutrition.kcal') }}</label>
                                <input type="number" inputmode="decimal" step="0.1" min="0" max="1000" name="items[{{ $i }}][manual][kcal]" value="{{ old("items.$i.manual.kcal") }}">
                            </div>
                        </div>
                        <div class="field-row">
                            <div class="field">
                                <label>{{ __('nutrition.protein') }}</label>
                                <input type="number" inputmode="decimal" step="0.1" min="0" max="100" name="items[{{ $i }}][manual][protein]" value="{{ old("items.$i.manual.protein") }}">
                            </div>
                            <div class="field">
                                <label>{{ __('nutrition.fat') }}</label>
                                <input type="number" inputmode="decimal" step="0.1" min="0" max="100" name="items[{{ $i }}][manual][fat]" value="{{ old("items.$i.manual.fat") }}">
                            </div>
                            <div class="field">
                                <label>{{ __('nutrition.carbs') }}</label>
                                <input type="number" inputmode="decimal" step="0.1" min="0" max="100" name="items[{{ $i }}][manual][carbs]" value="{{ old("items.$i.manual.carbs") }}">
                            </div>
                        </div>
                        <div class="field">
                            <label>{{ __('confirm.barcode') }} <span class="caption">({{ __('confirm.barcode_optional') }})</span></label>
                            <input type="text" inputmode="numeric" name="items[{{ $i }}][manual][barcode]" value="{{ old("items.$i.manual.barcode") }}">
                            <p class="caption">{{ __('confirm.barcode_hint') }}</p>
                        </div>
                        @foreach (['kcal', 'protein', 'fat', 'carbs', 'barcode'] as $field)
                            @error("items.$i.manual.$field")<p class="field-error">{{ $message }}</p>@enderror
                        @endforeach
                    </div>

                    {{-- An explicit skip, so choosing not to log a dish is also a choice. --}}
                    <label class="source source--skip">
                        <input type="radio" name="items[{{ $i }}][candidate]" value="skip" data-confirm-source>
                        <span class="source__body">{{ __('confirm.skip') }}</span>
                    </label>
                </div>
            </div>
        @endforeach

        {{-- Disabled until every dish has a choice: a dish cannot be logged
             without a source. The gating is JavaScript; the server also refuses
             to log a dish that carries no chosen source. --}}
        <button class="btn btn--block" type="submit" data-confirm-submit disabled>{{ __('confirm.submit') }}</button>
    </form>
@endsection
