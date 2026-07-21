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
                            <tr><td colspan="4" class="muted">No matches — enter the values from the label below.</td></tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="manual-entry" style="margin-top:12px">
                    <label>
                        <input type="radio" name="items[{{ $i }}][candidate]" value="manual" @checked($item['candidates'] === [])>
                        Enter values from the label
                        <span class="badge">Entered by hand</span>
                    </label>
                    <p class="muted" style="margin:4px 0 8px">
                        Verified, not an estimate: you read these off the package and the
                        model invented nothing. Per 100&nbsp;g.
                    </p>
                    <div class="row">
                        <div>
                            <label>Name</label>
                            <input type="text" name="items[{{ $i }}][manual][name]" value="{{ old("items.$i.manual.name", $item['name']) }}">
                        </div>
                        <div>
                            <label>kcal</label>
                            <input type="number" step="0.1" min="0" max="1000" name="items[{{ $i }}][manual][kcal]" value="{{ old("items.$i.manual.kcal") }}">
                        </div>
                        <div>
                            <label>Protein</label>
                            <input type="number" step="0.1" min="0" max="100" name="items[{{ $i }}][manual][protein]" value="{{ old("items.$i.manual.protein") }}">
                        </div>
                        <div>
                            <label>Fat</label>
                            <input type="number" step="0.1" min="0" max="100" name="items[{{ $i }}][manual][fat]" value="{{ old("items.$i.manual.fat") }}">
                        </div>
                        <div>
                            <label>Carbs</label>
                            <input type="number" step="0.1" min="0" max="100" name="items[{{ $i }}][manual][carbs]" value="{{ old("items.$i.manual.carbs") }}">
                        </div>
                    </div>
                    @foreach (['kcal', 'protein', 'fat', 'carbs'] as $field)
                        @error("items.$i.manual.$field")<p class="estimate-tag">{{ $message }}</p>@enderror
                    @endforeach
                </div>
            </div>
        @endforeach

        <button type="submit">Log selected</button>
    </form>
@endsection
