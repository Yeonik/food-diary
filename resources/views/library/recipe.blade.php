@extends('layouts.app')

@section('title', $recipe ? __('library.recipe_edit_title') : __('library.recipe_new_title'))

@section('content')
    @php
        $action = $recipe ? route('library.recipe.update', $recipe) : route('library.recipe.store');

        // Rows to draw: a validation redisplay wins; then a draft assembled
        // through the ingredient search (its rows, including one just added);
        // then the saved recipe; then a single empty row for a new one.
        $draftRows = ($draft ?? null)?->formRows();
        $existing = old('ingredients', $draftRows ?? ($recipe
            ? $recipe->ingredients->map(fn ($line) => ['item_id' => $line->ingredient_id, 'grams' => $line->grams])->all()
            : [['item_id' => '', 'grams' => '']]));

        // Name and cooked weight likewise prefer the draft, so searching for an
        // ingredient does not lose what was typed.
        $nameValue = old('name', ($draft ?? null)?->name ?: $recipe?->name);
        $cookedValue = old('cooked_weight_g', ($draft ?? null)?->cookedWeight ?: $recipe?->cooked_weight_g);
    @endphp

    <div class="narrow600 vform">
        <p class="caption">{{ __('library.recipe_intro') }}</p>

        <form method="post" action="{{ $action }}" class="vform">
            @csrf
            @if ($recipe) @method('PATCH') @endif

            {{-- Carried to the ingredient search so it survives the round trip. --}}
            @if ($recipe)<input type="hidden" name="recipe_id" value="{{ $recipe->id }}">@endif

            <x-field name="name" :label="__('library.recipe_name')" :value="$nameValue" required />

            <div>
                <div class="flabel">{{ __('library.ingredients') }}</div>
                <div class="ing-list" id="ingredients">
                    @foreach ($existing as $i => $line)
                        <div class="ing">
                            <select class="field" style="flex:1;min-width:0" name="ingredients[{{ $i }}][item_id]" required
                                    aria-label="{{ __('library.col_item') }}">
                                <option value="">{{ __('library.choose') }}</option>
                                @foreach ($ingredients as $option)
                                    <option value="{{ $option->id }}" @selected((string) $line['item_id'] === (string) $option->id)>{{ $option->name }}</option>
                                @endforeach
                            </select>
                            <input type="number" class="field" style="width:110px;flex:none" step="0.1" min="0.1"
                                   name="ingredients[{{ $i }}][grams]" value="{{ $line['grams'] }}" required
                                   aria-label="{{ __('library.col_grams') }}">
                            <button type="button" class="ing-del" onclick="this.closest('.ing').remove()"
                                    aria-label="{{ __('library.remove') }}" title="{{ __('library.remove') }}">
                                <x-icon name="delete" width="13" height="13" />
                            </button>
                        </div>
                    @endforeach
                </div>
                {{-- The plus is the button's glyph, not part of the phrase, so it
                     lives here and not in the translation — where it once did, and
                     printed twice. .add-dashed spaces the two as flex children. --}}
                <button type="button" class="add-dashed" onclick="addIngredientRow()">
                    <span aria-hidden="true">+</span>{{ __('library.add_ingredient') }}
                </button>

                {{-- Find an ingredient in the databases when it is not in the
                     library yet — the reason recipes were hard to build with an
                     empty library. This submits the whole recipe form to the
                     search (formaction), so the name, the cooked weight and the
                     rows so far are captured before leaving; the search hands
                     back candidates to choose from, and the chosen one is
                     promoted into the library and added here. A round trip, not
                     a background fetch. --}}
                <div class="ing-search">
                    <input type="search" class="field" name="query" style="flex:1;min-width:0"
                           placeholder="{{ __('library.ingredient_search_placeholder') }}"
                           aria-label="{{ __('library.ingredient_search') }}">
                    {{-- formnovalidate: searching for an ingredient must not
                         require the cooked weight or name to be filled first —
                         the person may well be searching before they know the
                         cooked weight. The search validates only its own query. --}}
                    <button type="submit" class="btn btn-s" formnovalidate
                            formaction="{{ route('library.recipe.ingredient.search') }}">
                        <x-icon name="search" width="14" height="14" /> {{ __('library.ingredient_find') }}
                    </button>
                </div>
            </div>

            {{-- The weight of the cooked dish, and the reason the numbers above
                 can be trusted: they are divided by this, not by the raw
                 ingredients, so a dish that absorbs or boils off water reads
                 correctly. Weigh it after cooking. The raw total is shown only as
                 a reference — a dish legitimately weighs more or less than its
                 ingredients — so a mistyped weight stands out without being
                 rejected. --}}
            <x-field type="text" name="cooked_weight_g" inputmode="decimal"
                     :label="__('library.cooked_weight')"
                     :value="$cookedValue"
                     :hint="isset($rawSum) && $rawSum > 0
                        ? __('library.cooked_weight_hint_raw', ['grams' => \App\Support\Format::grams($rawSum)])
                        : __('library.cooked_weight_hint')"
                     required />

            @if ($incomplete ?? false)
                {{-- A recipe from before the cooked weight existed, opened to be
                     completed. It shows no total on purpose — there is no honest
                     one until the weight above is filled in — and says so rather
                     than leaving the space blank. --}}
                <p class="caption">{{ __('library.incomplete_notice') }}</p>
            @endif

            @if ($total !== null)
                {{-- What the ingredients above come to. A recipe stores no numbers
                     of its own; these are computed from them, which is why editing
                     an ingredient later cannot rewrite an entry already logged. --}}
                <div class="total-bar">
                    <span class="l">{{ __('library.recipe_total') }}</span>
                    {{-- Numbers dark, their units quiet, and space rather than
                         punctuation between the calories and the macros — the same
                         reading order as the day summary's macro rows. --}}
                    <span class="v">
                        <span class="tb-kcal"><b>{{ \App\Support\Format::kcal($total->kcal) }}</b> {{ __('nutrition.kcal') }}</span>
                        <span class="tb-macros">
                            <span>{{ __('nutrition.p') }} <b>{{ \App\Support\Format::macro($total->proteinG) }}</b></span>
                            <span>{{ __('nutrition.f') }} <b>{{ \App\Support\Format::macro($total->fatG) }}</b></span>
                            <span>{{ __('nutrition.c') }} <b>{{ \App\Support\Format::macro($total->carbsG) }}</b></span>
                        </span>
                    </span>
                </div>
            @endif

            <div class="actions-end" style="margin-top:0">
                <x-button variant="secondary" href="{{ route('library.index') }}">{{ __('common.cancel') }}</x-button>
                <x-button type="submit">{{ __('library.save_recipe') }}</x-button>
            </div>
        </form>
    </div>

    <template id="ingredient-row">
        <div class="ing">
            <select class="field" style="flex:1;min-width:0" required aria-label="{{ __('library.col_item') }}">
                <option value="">{{ __('library.choose') }}</option>
                @foreach ($ingredients as $option)
                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                @endforeach
            </select>
            <input type="number" class="field" style="width:110px;flex:none" step="0.1" min="0.1" required
                   aria-label="{{ __('library.col_grams') }}">
            <button type="button" class="ing-del" onclick="this.closest('.ing').remove()"
                    aria-label="{{ __('library.remove') }}" title="{{ __('library.remove') }}">
                <x-icon name="delete" width="13" height="13" />
            </button>
        </div>
    </template>

    <script>
        // Minimal, unobtrusive: clone the template row and name its fields with a
        // fresh index so the server sees another ingredient.
        let nextIndex = {{ count($existing) }};
        function addIngredientRow() {
            const tpl = document.getElementById('ingredient-row').content.cloneNode(true);
            const row = tpl.querySelector('.ing');
            row.querySelector('select').name = `ingredients[${nextIndex}][item_id]`;
            row.querySelector('input').name = `ingredients[${nextIndex}][grams]`;
            document.getElementById('ingredients').appendChild(row);
            nextIndex++;
        }
    </script>
@endsection
