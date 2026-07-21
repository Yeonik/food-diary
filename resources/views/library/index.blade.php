@extends('layouts.app')

@section('title', 'Library')

@section('content')
    <h1>Personal library</h1>
    <p class="muted">
        Foods you confirmed, corrected, or defined as recipes — consulted first when
        resolving a meal. <a href="{{ route('library.create') }}">Add an item</a> ·
        <a href="{{ route('library.recipe.create') }}">Define a recipe</a>
    </p>

    <form method="get" action="{{ route('library.index') }}" class="panel row">
        <div style="flex:1">
            <label for="q">Search</label>
            <input type="text" name="q" id="q" value="{{ $search }}" style="width:100%">
        </div>
        <div><button type="submit">Search</button></div>
    </form>

    <table>
        <thead><tr><th>Name</th><th>Kind</th><th>Origin</th><th></th></tr></thead>
        <tbody>
            @forelse ($items as $item)
                <tr>
                    <td>{{ $item->name }}</td>
                    <td>{{ $item->isRecipe() ? 'Recipe' : 'Direct' }}</td>
                    <td class="muted">{{ $item->origin?->label() ?? ($item->isRecipe() ? 'Computed' : '—') }}</td>
                    <td>
                        @if ($item->isRecipe())
                            <a class="btn link" href="{{ route('library.recipe.edit', $item) }}">Edit</a>
                        @else
                            <a class="btn link" href="{{ route('library.edit', $item) }}">Edit</a>
                        @endif
                        <form class="inline-form" method="post" action="{{ route('library.destroy', $item) }}"
                              onsubmit="return confirm('Remove this item?')">
                            @csrf @method('DELETE')
                            <button class="link" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">The library is empty.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top:14px">{{ $items->links() }}</div>
@endsection
