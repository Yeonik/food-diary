@extends('layouts.app')

@section('title', $recipe ? __('library.recipe_edit_title') : __('library.recipe_new_title'))

@section('content')
    @php
        $action = $recipe ? route('library.recipe.update', $recipe) : route('library.recipe.store');
        $existing = old('ingredients', $recipe
            ? $recipe->ingredients->map(fn ($line) => ['item_id' => $line->ingredient_id, 'grams' => $line->grams])->all()
            : [['item_id' => '', 'grams' => '']]);
    @endphp

    <div class="narrow600 vform">
        <p class="caption">{{ __('library.recipe_intro') }}</p>

        <form method="post" action="{{ $action }}" class="vform">
            @csrf
            @if ($recipe) @method('PATCH') @endif

            <x-field name="name" :label="__('library.recipe_name')" :value="old('name', $recipe?->name)" required />

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
                <button type="button" class="add-dashed" onclick="addIngredientRow()">+ {{ __('library.add_ingredient') }}</button>
            </div>

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
