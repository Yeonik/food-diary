<?php

declare(strict_types=1);

return [
    'title' => 'Library',
    'subtitle' => 'Foods you confirmed, corrected or defined — consulted first.',
    'search' => 'Search products',
    'search_action' => 'Search',
    'all_products' => 'All products',
    'new_product' => 'New product',
    'define_recipe' => 'Define a recipe',
    'recipe' => 'Recipe',
    'empty_title' => 'The library is empty',
    'empty_body' => 'Products appear here after you add one by hand or confirm one from Open Food Facts.',
    'confirm_delete' => 'Remove this item?',

    // Direct item — create and correct.
    'add_title' => 'Add a library item',
    'add_subtitle' => 'A direct nutrient profile, per 100 g.',
    'edit_title' => 'Correct “:name”',
    'edit_subtitle' => 'Corrections apply to future logs. Past entries keep the numbers they were logged with.',
    'field_name' => 'Name',
    'field_alt_name' => 'Name in another language',
    'alt_name_hint' => 'A Russian package name, say — a photo can then find this item by either name.',
    'alt_name_hint_edit' => 'A photo can find this item by either name.',
    'field_barcode' => 'Barcode',
    'barcode_hint' => 'Type it once and this product matches exactly next time — no camera needed.',
    'barcode_hint_edit' => 'The stable id that makes this product match exactly — no camera needed.',
    'per_100g_kcal' => 'kcal / 100 g',
    'per_100g_protein' => 'Protein g / 100 g',
    'per_100g_fat' => 'Fat g / 100 g',
    'per_100g_carbs' => 'Carbs g / 100 g',

    // Recipe.
    'recipe_new_title' => 'Define a recipe',
    'recipe_edit_title' => 'Edit recipe',
    'recipe_intro' => 'A recipe is a list of library items with weights. Its profile is computed from them, so a home-cooked dish behaves like any other food once defined. An ingredient may itself be a recipe; cycles are rejected.',
    'recipe_name' => 'Recipe name',
    'ingredients' => 'Ingredients',
    'col_item' => 'Item',
    'col_grams' => 'Grams',
    'choose' => '— choose —',
    'remove' => 'Remove',
    'add_ingredient' => '+ Add ingredient',
    'save_recipe' => 'Save recipe',

    // Outcomes.
    'item_added' => 'Item added to the library.',
    'item_corrected' => 'Item corrected. Past entries are unchanged.',
    'item_removed' => 'Item removed.',
    'recipe_saved' => 'Recipe ":name" saved.',
    'recipe_updated' => 'Recipe updated. Past entries are unchanged.',
    'duplicates_merged' => 'Duplicates merged.',
    'cycle_error' => 'Those ingredients form a cycle.',
    'in_use_error' => 'This item is used by a recipe; remove it there first.',
    'manual_is_verified' => 'A product you enter by hand counts as verified — you read the numbers, the model did not invent them.',
    'recipe_total' => 'Per 100 g',
];
