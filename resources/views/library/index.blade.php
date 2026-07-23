@extends('layouts.app')

@section('title', __('library.title'))

@section('content')
    <form method="get" action="{{ route('library.index') }}" class="lib-search">
        <label class="searchbox">
            <x-icon name="search" />
            <span class="visually-hidden">{{ __('library.search') }}</span>
            <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('library.search') }}">
        </label>
        <x-button type="submit" variant="secondary">{{ __('library.search_action') }}</x-button>
        <x-button variant="secondary" href="{{ route('library.recipe.create') }}">{{ __('library.define_recipe') }}</x-button>
        <x-button href="{{ route('library.create') }}" icon="plus">{{ __('library.new_product') }}</x-button>
    </form>

    @if ($items->isEmpty())
        <x-empty-state icon="library" :title="__('library.empty_title')" :body="__('library.empty_body')">
            <x-slot:action>
                <x-button href="{{ route('library.create') }}" icon="plus">{{ __('library.new_product') }}</x-button>
            </x-slot:action>
        </x-empty-state>
    @else
        <div class="eyebrow muted">{{ __('library.all_products') }}</div>
        <div class="prod-grid">
            @foreach ($items as $item)
                <div class="prod">
                    <div style="flex:1;min-width:0">
                        <div class="t">{{ $item->name }}</div>
                        <div class="m">
                            @if ($item->kcal_per_100g !== null)
                                <span class="k">{{ \App\Support\Format::kcal($item->kcal_per_100g) }} {{ __('nutrition.kcal') }} / {{ __('nutrition.per_100g') }}</span>
                            @elseif ($item->isRecipe())
                                <span class="k">{{ __('library.recipe') }}</span>
                            @endif
                            @if ($item->origin)
                                <x-source-chip :source="$item->origin" />
                            @endif
                        </div>
                    </div>
                    <x-icon-button :label="__('common.edit')" icon="edit"
                                   href="{{ $item->isRecipe() ? route('library.recipe.edit', $item) : route('library.edit', $item) }}" />
                    <form method="post" action="{{ route('library.destroy', $item) }}"
                          onsubmit="return confirm('{{ __('library.confirm_delete') }}')">
                        @csrf @method('DELETE')
                        <x-icon-button type="submit" tone="danger" :label="__('common.delete')" icon="delete" />
                    </form>
                </div>
            @endforeach
        </div>

        <div class="pagination">{{ $items->links() }}</div>
    @endif
@endsection
