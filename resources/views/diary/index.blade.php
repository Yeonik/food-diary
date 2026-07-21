@extends('layouts.app')

@section('title', 'Diary — '.$date->format('j M Y'))

@section('content')
    <h1>{{ $date->isToday() ? 'Today' : $date->format('l, j F Y') }}</h1>
    <p class="muted">
        <a href="{{ route('diary.index', ['date' => $previous->toDateString()]) }}">&larr; {{ $previous->format('j M') }}</a>
        &nbsp;·&nbsp;
        <a href="{{ route('diary.index', ['date' => $next->toDateString()]) }}">{{ $next->format('j M') }} &rarr;</a>
    </p>

    <div class="panel totals">
        <div><label>Logged</label><span class="big">{{ number_format($summary->kcal) }}</span> kcal</div>
        @if ($summary->hasGoal())
            <div><label>Remaining</label><span class="big">{{ number_format($summary->remainingKcal) }}</span> kcal</div>
        @endif
        <div><label>Protein</label>{{ number_format($summary->proteinG, 1) }} g</div>
        <div><label>Fat</label>{{ number_format($summary->fatG, 1) }} g</div>
        <div><label>Carbs</label>{{ number_format($summary->carbsG, 1) }} g</div>
    </div>

    @if ($summary->hasEstimates)
        <p class="estimate-tag">Some entries below are unverified estimates.</p>
    @endif

    @php $empty = collect($entriesByMeal)->every(fn ($rows) => $rows->isEmpty()); @endphp

    @foreach ($mealTypes as $meal)
        @php $rows = $entriesByMeal[$meal->value]; @endphp
        @if ($rows->isNotEmpty())
            <h2>{{ $meal->label() }}</h2>
            <table>
                <thead><tr><th>Item</th><th>Grams</th><th>kcal</th><th>Source</th><th></th></tr></thead>
                <tbody>
                    @foreach ($rows as $entry)
                        <tr class="{{ $entry->isVerified() ? '' : 'estimate' }}">
                            <td>{{ $entry->name }}</td>
                            <td>{{ number_format($entry->grams) }}</td>
                            <td>{{ number_format($entry->kcal) }}</td>
                            <td>
                                <span class="badge">{{ $entry->source->label() }}</span>
                                @unless ($entry->isVerified())<span class="estimate-tag">unverified</span>@endunless
                            </td>
                            <td>
                                <a class="btn link" href="{{ route('entries.edit', $entry) }}">Edit</a>
                                <form class="inline-form" method="post" action="{{ route('entries.destroy', $entry) }}"
                                      onsubmit="return confirm('Delete this entry?')">
                                    @csrf @method('DELETE')
                                    <button class="link" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach

    @if ($empty)
        <p class="muted">Nothing logged for this day.
            <a href="{{ route('log.photo') }}">Log a photo</a> or
            <a href="{{ route('log.manual') }}">add manually</a>.</p>
    @endif
@endsection
