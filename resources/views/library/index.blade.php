@extends('layouts.app')

@section('title', __('library.title'))

@section('content')
    {{-- Search, then the two ways to add something. The field submits on Enter,
         as the build's search box does — no separate button. --}}
    <form method="get" action="{{ route('library.index') }}" class="lib-search">
        <label class="searchbox">
            <x-icon name="search" />
            <span class="visually-hidden">{{ __('library.search') }}</span>
            <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('library.search') }}">
        </label>
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
                {{-- No picture: an Open Food Facts thumbnail would be fetched from
                     their servers every time this list is opened, which tells them
                     what is in the library. Thumbnails stay on the confirm screen,
                     where a request for that product is already going out. --}}
                <div class="prod">
                    <div style="flex:1;min-width:0">
                        <div class="t">{{ $item->name }}</div>
                        <div class="m">
                            @php
                                $kcal = $item->isRecipe() ? ($recipeKcal[$item->id] ?? null) : $item->kcal_per_100g;
                            @endphp
                            @if ($kcal !== null)
                                <span class="k">{{ \App\Support\Format::kcal($kcal) }} {{ __('nutrition.kcal') }} / {{ __('nutrition.per_100g') }}</span>
                            @endif

                            @if ($item->isRecipe())
                                {{-- A recipe's numbers come from its ingredients — that is
                                     its provenance, and it is a verified one. --}}
                                <span class="chip"><span aria-hidden="true">✓</span>{{ __('library.recipe') }}</span>
                            @elseif ($item->origin)
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

        {{ $items->links('pagination.pager') }}
    @endif
@endsection
