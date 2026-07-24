@extends('layouts.app')

@section('title', __('library.ingredient_choose_title'))

@section('content')
    <div class="narrow600">
        <p class="conf-lead">{{ __('library.ingredient_choose_intro', ['query' => $query]) }}</p>

        @if (empty($candidates))
            {{-- Nothing answered. Not an estimate offered in its place — an
                 ingredient must be a real number, so the honest move is to say
                 nothing was found and let the person try another name or enter it
                 by hand from the library. --}}
            <x-card style="margin:16px 0">
                <p class="s">{{ __('library.ingredient_none', ['query' => $query]) }}</p>
            </x-card>

            <div class="actions-end" style="margin-top:0">
                <form method="post" action="{{ route('library.recipe.ingredient.cancel') }}">
                    @csrf
                    <x-button variant="secondary" type="submit">{{ __('common.back') }}</x-button>
                </form>
            </div>
        @else
            {{-- Pick one and give its weight in the dish. The numbers shown come
                 from the source's own record and are what the promoted item will
                 carry — the form sends only which candidate and how many grams,
                 never the numbers. Nothing is pre-selected: the same rule the
                 confirm screen keeps. --}}
            <form method="post" action="{{ route('library.recipe.ingredient.add') }}" class="vform">
                @csrf

                <div class="cand-headrow">
                    <span>{{ __('confirm.match_source') }}</span>
                    <span>{{ __('nutrition.per_100g') }}</span>
                </div>

                <div class="cand-list" style="margin:14px 0">
                    @foreach ($candidates as $c => $candidate)
                        <label class="crow">
                            <input type="radio" name="candidate" value="{{ $c }}" required>
                            <span class="cring"><span></span></span>
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
                </div>

                <x-field type="number" name="grams" :label="__('library.ingredient_grams')"
                         step="0.1" min="0.1" :value="old('grams')" required
                         :hint="__('library.ingredient_grams_hint')" />

                <div class="actions-end" style="margin-top:0">
                    <x-button type="submit">{{ __('library.ingredient_add_to_recipe') }}</x-button>
                </div>
            </form>

            <form method="post" action="{{ route('library.recipe.ingredient.cancel') }}" style="margin-top:12px">
                @csrf
                <x-button variant="secondary" type="submit">{{ __('common.cancel') }}</x-button>
            </form>
        @endif
    </div>
@endsection
