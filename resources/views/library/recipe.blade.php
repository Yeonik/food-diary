@extends('layouts.app')

@section('title', $recipe ? __('library.recipe_edit_title') : __('library.recipe_new_title'))

@section('content')
    <h1>{{ $recipe ? __('library.recipe_edit_title') : __('library.recipe_new_title') }}</h1>
    <p class="muted">{{ __('library.recipe_intro') }}</p>

    @php
        $action = $recipe ? route('library.recipe.update', $recipe) : route('library.recipe.store');
        $existing = old('ingredients', $recipe
            ? $recipe->ingredients->map(fn ($line) => ['item_id' => $line->ingredient_id, 'grams' => $line->grams])->all()
            : [['item_id' => '', 'grams' => '']]);
    @endphp

    <form method="post" action="{{ $action }}" class="panel">
        @csrf
        @if ($recipe) @method('PATCH') @endif
        <div>
            <label>{{ __('library.recipe_name') }}</label>
            <input type="text" name="name" value="{{ old('name', $recipe?->name) }}" style="width:100%" required>
        </div>

        <h2>{{ __('library.ingredients') }}</h2>
        <table id="ingredients">
            <thead><tr><th>{{ __('library.col_item') }}</th><th>{{ __('library.col_grams') }}</th><th></th></tr></thead>
            <tbody>
                @foreach ($existing as $i => $line)
                    <tr>
                        <td>
                            <select name="ingredients[{{ $i }}][item_id]" required>
                                <option value="">{{ __('library.choose') }}</option>
                                @foreach ($ingredients as $option)
                                    <option value="{{ $option->id }}" @selected((string) $line['item_id'] === (string) $option->id)>{{ $option->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td><input type="number" step="0.1" min="0.1" name="ingredients[{{ $i }}][grams]" value="{{ $line['grams'] }}" required></td>
                        <td><button type="button" class="link" onclick="this.closest('tr').remove()">{{ __('library.remove') }}</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p><button type="button" class="link" onclick="addIngredientRow()">{{ __('library.add_ingredient') }}</button></p>

        <p style="margin-top:12px"><button type="submit">{{ __('library.save_recipe') }}</button>
            <a class="btn link" href="{{ route('library.index') }}">{{ __('common.cancel') }}</a></p>
    </form>

    <template id="ingredient-row">
        <tr>
            <td>
                <select required>
                    <option value="">{{ __('library.choose') }}</option>
                    @foreach ($ingredients as $option)
                        <option value="{{ $option->id }}">{{ $option->name }}</option>
                    @endforeach
                </select>
            </td>
            <td><input type="number" step="0.1" min="0.1" required></td>
            <td><button type="button" class="link" onclick="this.closest('tr').remove()">{{ __('library.remove') }}</button></td>
        </tr>
    </template>

    <script>
        // Minimal, unobtrusive: clone the template row and name its fields with a
        // fresh index so the server sees another ingredient.
        let nextIndex = {{ count($existing) }};
        function addIngredientRow() {
            const tpl = document.getElementById('ingredient-row').content.cloneNode(true);
            const row = tpl.querySelector('tr');
            row.querySelector('select').name = `ingredients[${nextIndex}][item_id]`;
            row.querySelector('input').name = `ingredients[${nextIndex}][grams]`;
            document.querySelector('#ingredients tbody').appendChild(row);
            nextIndex++;
        }
    </script>
@endsection
