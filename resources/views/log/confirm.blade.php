@extends('layouts.app')

@section('title', __('confirm.title'))

@section('content')
    <div class="narrow">
        <p class="conf-lead">{{ __('confirm.intro') }}</p>

        <form method="post" action="{{ route('log.confirm.store') }}">
            @csrf

            <div class="conf-meta">
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

            @foreach ($items as $i => $item)
                @php $hasVerified = collect($item['candidates'])->contains(fn ($c): bool => $c['verified']); @endphp
                <div class="conf-card">
                    <div class="conf-head">
                        <div class="t">{{ $item['name'] }}</div>
                        <div class="portion">
                            <label class="rec-g" for="grams-{{ $i }}">{{ __('confirm.grams') }}</label>
                            <input type="number" id="grams-{{ $i }}" name="items[{{ $i }}][grams]"
                                   inputmode="decimal" step="0.1" min="0.1" max="5000" value="{{ $item['grams'] }}">
                            <span class="rec-g">{{ __('nutrition.g') }}</span>
                        </div>
                    </div>

                    @unless ($hasVerified)
                        <div class="zero-note">{{ __('confirm.no_matches') }}</div>
                    @endunless

                    <div class="cand-headrow">
                        <span>{{ __('confirm.match_source') }}</span>
                        <span>{{ __('nutrition.per_100g') }}</span>
                    </div>

                    <div class="cand-list">
                        {{-- Nothing is selected by default — not USDA, not Open Food Facts,
                             not the top-ranked match. The user chooses per dish. --}}
                        @foreach ($item['candidates'] as $c => $candidate)
                            <label class="crow">
                                <input type="radio" name="items[{{ $i }}][candidate]" value="{{ $c }}" data-confirm-source>
                                <span class="cring"><span></span></span>
                                {{-- Open Food Facts thumbnail, pulled by link (never copied
                                     here) and shown only on this screen, where a request for
                                     this product is already in flight. Absent link or a load
                                     failure just removes it — no broken-image placeholder. --}}
                                @if (! empty($candidate['image_url']))
                                    <span class="cthumb">
                                        <img src="{{ $candidate['image_url'] }}" alt="" loading="lazy" onerror="this.parentNode.remove()">
                                    </span>
                                @endif
                                <span class="cbody">
                                    <span class="n">{{ $candidate['label'] }}</span>
                                    <x-source-chip :source="$candidate['source']" />
                                    @if (! empty($candidate['matched_via']))
                                        <span class="rec-g">{{ __('confirm.matched_on', ['term' => $candidate['matched_via']]) }}</span>
                                    @endif
                                </span>
                                <span class="cline">
                                    {{ \App\Support\Format::kcal($candidate['kcal']) }} {{ __('nutrition.kcal') }} ·
                                    {{ \App\Support\Format::macro($candidate['protein']) }} /
                                    {{ \App\Support\Format::macro($candidate['fat']) }} /
                                    {{ \App\Support\Format::macro($candidate['carbs']) }}
                                </span>
                            </label>
                        @endforeach

                        {{-- Hand-entered label values — always available, verified. --}}
                        <label class="crow">
                            <input type="radio" name="items[{{ $i }}][candidate]" value="manual" data-confirm-source>
                            <span class="cring"><span></span></span>
                            <span class="cbody">
                                <span class="n">{{ __('confirm.manual_option') }}</span>
                                <x-source-chip source="manual" />
                            </span>
                        </label>

                        <div class="manual-fields">
                            <div class="two">
                                <div>
                                    <label class="flabel" for="m-name-{{ $i }}">{{ __('confirm.name') }}</label>
                                    <input type="text" class="field" id="m-name-{{ $i }}" name="items[{{ $i }}][manual][name]" value="{{ old("items.$i.manual.name", $item['name']) }}">
                                </div>
                                <div>
                                    <label class="flabel" for="m-kcal-{{ $i }}">{{ __('nutrition.kcal') }}</label>
                                    <input type="number" class="field" id="m-kcal-{{ $i }}" inputmode="decimal" step="0.1" min="0" max="1000" name="items[{{ $i }}][manual][kcal]" value="{{ old("items.$i.manual.kcal") }}">
                                </div>
                            </div>
                            <div class="two" style="margin-top:12px">
                                <div>
                                    <label class="flabel" for="m-p-{{ $i }}">{{ __('nutrition.protein') }}</label>
                                    <input type="number" class="field" id="m-p-{{ $i }}" inputmode="decimal" step="0.1" min="0" max="100" name="items[{{ $i }}][manual][protein]" value="{{ old("items.$i.manual.protein") }}">
                                </div>
                                <div>
                                    <label class="flabel" for="m-f-{{ $i }}">{{ __('nutrition.fat') }}</label>
                                    <input type="number" class="field" id="m-f-{{ $i }}" inputmode="decimal" step="0.1" min="0" max="100" name="items[{{ $i }}][manual][fat]" value="{{ old("items.$i.manual.fat") }}">
                                </div>
                                <div>
                                    <label class="flabel" for="m-c-{{ $i }}">{{ __('nutrition.carbs') }}</label>
                                    <input type="number" class="field" id="m-c-{{ $i }}" inputmode="decimal" step="0.1" min="0" max="100" name="items[{{ $i }}][manual][carbs]" value="{{ old("items.$i.manual.carbs") }}">
                                </div>
                            </div>
                            <div style="margin-top:12px">
                                <label class="flabel" for="m-bar-{{ $i }}">{{ __('confirm.barcode') }} <span class="opt">{{ __('confirm.barcode_optional') }}</span></label>
                                <input type="text" class="field" id="m-bar-{{ $i }}" inputmode="numeric" name="items[{{ $i }}][manual][barcode]" value="{{ old("items.$i.manual.barcode") }}">
                                <span class="fhint">{{ __('confirm.barcode_hint') }}</span>
                            </div>
                            @foreach (['kcal', 'protein', 'fat', 'carbs', 'barcode'] as $field)
                                @error("items.$i.manual.$field")<p class="field-error">{{ $message }}</p>@enderror
                            @endforeach
                        </div>

                        {{-- An explicit skip, so choosing not to log a dish is also a choice. --}}
                        <label class="crow">
                            <input type="radio" name="items[{{ $i }}][candidate]" value="skip" data-confirm-source>
                            <span class="cring"><span></span></span>
                            <span class="cbody"><span class="n">{{ __('confirm.skip') }}</span></span>
                        </label>
                    </div>
                </div>
            @endforeach

            {{-- Disabled until every dish has a choice: a dish cannot be logged
                 without a source. The gating is JavaScript; the server also refuses
                 to log a dish that carries no chosen source. --}}
            <div class="actions-end">
                <span class="record-hint" data-confirm-hint>{{ __('confirm.choose_hint') }}</span>
                <x-button type="submit" data-confirm-submit disabled>{{ __('confirm.submit') }}</x-button>
            </div>
        </form>
    </div>
@endsection
