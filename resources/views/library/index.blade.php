@extends('layouts.app')

@section('title', __('library.title'))

@section('content')
    <h1>{{ __('library.title') }}</h1>
    <p class="muted">{{ __('library.subtitle') }}</p>

    <form method="get" action="{{ route('library.index') }}" class="card">
        <div class="field">
            <label for="q">{{ __('library.search') }}</label>
            <input type="search" name="q" id="q" value="{{ $search }}">
        </div>
        <div class="field-row">
            <button class="btn" type="submit">{{ __('library.search_action') }}</button>
            <a class="btn btn--ghost" href="{{ route('library.create') }}">
                <x-icon name="plus" /> {{ __('library.new_product') }}
            </a>
            <a class="btn btn--ghost" href="{{ route('library.recipe.create') }}">{{ __('library.define_recipe') }}</a>
        </div>
    </form>

    @if ($items->isEmpty())
        <div class="empty">
            <x-icon name="library" class="empty__icon" />
            <div class="empty__title">{{ __('library.empty_title') }}</div>
            <p class="empty__body">{{ __('library.empty_body') }}</p>
            <div class="empty__actions">
                <a class="btn" href="{{ route('library.create') }}"><x-icon name="plus" /> {{ __('library.new_product') }}</a>
            </div>
        </div>
    @else
        <div class="section-title"><h2>{{ __('library.all_products') }}</h2></div>
        <ul class="list">
            @foreach ($items as $item)
                <li class="list__row">
                    <div class="list__body">
                        <div class="list__title">{{ $item->name }}</div>
                        <div class="caption">
                            @if ($item->kcal_per_100g !== null)
                                {{ \App\Support\Format::kcal($item->kcal_per_100g) }} {{ __('nutrition.kcal') }} / {{ __('nutrition.per_100g') }}
                            @elseif ($item->isRecipe())
                                {{ __('library.recipe') }}
                            @endif
                            @if ($item->origin)
                                · {{ __('source.'.$item->origin->value) }}
                            @endif
                        </div>
                    </div>
                    @if ($item->isRecipe())
                        <a class="btn btn--quiet" href="{{ route('library.recipe.edit', $item) }}">{{ __('common.edit') }}</a>
                    @else
                        <a class="btn btn--quiet" href="{{ route('library.edit', $item) }}">{{ __('common.edit') }}</a>
                    @endif
                    <form method="post" action="{{ route('library.destroy', $item) }}"
                          onsubmit="return confirm('{{ __('library.confirm_delete') }}')">
                        @csrf @method('DELETE')
                        <button class="btn btn--danger-quiet" type="submit">{{ __('common.delete') }}</button>
                    </form>
                </li>
            @endforeach
        </ul>

        <div class="pagination">{{ $items->links() }}</div>
    @endif
@endsection
