@extends('layouts.app')

@section('title', 'Confirm')

@section('content')
    <h1>Confirm</h1>
    <p class="muted">
        Pick the source for each item and adjust the weight. The numbers come from
        the source you choose — never invented. An estimate is shown only when no
        real source could answer, and it stays marked unverified.
    </p>

    <form method="post" action="{{ route('log.confirm.store') }}">
        @csrf
        <div class="panel row">
            <div>
                <label for="meal">Meal</label>
                <select name="meal" id="meal">
                    @foreach ($mealTypes as $meal)
                        <option value="{{ $meal->value }}">{{ $meal->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="date">Date</label>
                <input type="date" name="date" id="date" value="{{ now()->toDateString() }}">
            </div>
        </div>

        @foreach ($items as $i => $item)
            <div class="panel">
                <label>
                    <input type="checkbox" name="items[{{ $i }}][include]" value="1" checked>
                    Log &ldquo;{{ $item['name'] }}&rdquo;
                </label>
                <div class="row" style="margin-top:8px">
                    <div>
                        <label>Grams</label>
                        <input type="number" step="0.1" min="0.1" name="items[{{ $i }}][grams]" value="{{ $item['grams'] }}">
                    </div>
                </div>
                <table style="margin-top:10px">
                    <thead><tr><th></th><th>Match</th><th>Source</th><th>per 100 g</th></tr></thead>
                    <tbody>
                        @forelse ($item['candidates'] as $c => $candidate)
                            <tr class="{{ $candidate['verified'] ? '' : 'estimate' }}">
                                <td><input type="radio" name="items[{{ $i }}][candidate]" value="{{ $c }}" @checked($c === 0)></td>
                                <td>{{ $candidate['label'] }}</td>
                                <td>
                                    <span class="badge">{{ $candidate['source_label'] }}</span>
                                    @unless ($candidate['verified'])<span class="estimate-tag">unverified</span>@endunless
                                </td>
                                <td class="muted">
                                    {{ number_format($candidate['kcal']) }} kcal ·
                                    {{ number_format($candidate['protein'], 1) }}p /
                                    {{ number_format($candidate['fat'], 1) }}f /
                                    {{ number_format($candidate['carbs'], 1) }}c
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="muted">No matches. Add it to the library manually instead.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endforeach

        <button type="submit">Log selected</button>
    </form>
@endsection
