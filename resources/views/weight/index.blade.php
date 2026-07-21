@extends('layouts.app')

@section('title', 'Weight')

@section('content')
    <h1>Weight</h1>
    <p class="muted">A log and a line. No target, no verdict.</p>

    <form method="post" action="{{ route('weight.store') }}" class="panel row">
        @csrf
        <div><label>Date</label><input type="date" name="recorded_on" value="{{ now()->toDateString() }}" required></div>
        <div><label>Weight (kg)</label><input type="number" step="0.1" min="1" name="weight_kg" value="{{ old('weight_kg') }}" required></div>
        <div><button type="submit">Record</button></div>
    </form>

    @if (count($chart) > 0)
        <div class="panel">
            <svg viewBox="0 0 600 200" width="100%" height="200" role="img" aria-label="Weight over time" preserveAspectRatio="none">
                @if (count($chart) > 1)
                    <polyline fill="none" stroke="#33506b" stroke-width="2"
                              points="@foreach ($chart as $p){{ $p['x'] }},{{ $p['y'] }} @endforeach" />
                @endif
                @foreach ($chart as $p)
                    <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="3" fill="#33506b"><title>{{ $p['label'] }}</title></circle>
                @endforeach
            </svg>
        </div>
    @endif

    <table>
        <thead><tr><th>Date</th><th>Weight</th><th></th></tr></thead>
        <tbody>
            @forelse ($entries as $entry)
                <tr>
                    <td>{{ $entry->recorded_on->format('Y-m-d') }}</td>
                    <td>{{ $entry->weight_kg }} kg</td>
                    <td>
                        <form class="inline-form" method="post" action="{{ route('weight.destroy', $entry) }}"
                              onsubmit="return confirm('Remove this reading?')">
                            @csrf @method('DELETE')
                            <button class="link" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="muted">No readings yet.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
