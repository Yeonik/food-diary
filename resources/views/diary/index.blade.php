@extends('layouts.app')

@section('title', $date->isToday() ? __('day.today') : $date->translatedFormat('j F, l'))

@section('content')
    <div class="datestep">
        <a href="{{ route('diary.index', ['date' => $previous->toDateString()]) }}" aria-label="{{ $previous->translatedFormat('j F') }}">‹</a>
        <span class="datestep__label">{{ $date->isToday() ? __('day.today') : $date->translatedFormat('j F, l') }}</span>
        <a href="{{ route('diary.index', ['date' => $next->toDateString()]) }}" aria-label="{{ $next->translatedFormat('j F') }}">›</a>
    </div>

    <div class="card">
        <div class="summary">
            @if ($summary->hasGoal())
                <x-ring :eaten="$summary->kcal" :target="$goal->daily_kcal" />
                <div class="summary__figure">
                    <div class="summary__label">{{ __('day.eaten_today') }}</div>
                    <div class="summary__remaining">
                        {{ __('day.remaining') }}: {{ \App\Support\Format::kcal($summary->remainingKcal) }} {{ __('nutrition.kcal') }}
                    </div>
                </div>
            @else
                <div class="summary__figure">
                    <div class="summary__label">{{ __('day.eaten_today') }}</div>
                    <div class="summary__eaten">{{ \App\Support\Format::kcal($summary->kcal) }}
                        <span class="caption">{{ __('nutrition.kcal') }}</span></div>
                </div>
            @endif
        </div>
        <div class="summary__macros">
            <span class="summary__macro">{{ __('nutrition.protein') }} <b>{{ \App\Support\Format::macro($summary->proteinG) }}</b> {{ __('nutrition.g') }}</span>
            <span class="summary__macro">{{ __('nutrition.fat') }} <b>{{ \App\Support\Format::macro($summary->fatG) }}</b> {{ __('nutrition.g') }}</span>
            <span class="summary__macro">{{ __('nutrition.carbs') }} <b>{{ \App\Support\Format::macro($summary->carbsG) }}</b> {{ __('nutrition.g') }}</span>
        </div>
    </div>

    @if ($summary->hasEstimates)
        <p class="prov prov--estimate">{{ __('day.has_estimates') }}</p>
    @endif

    @if (! $hasAnyEntry)
        <div class="empty">
            <div class="empty__title">{{ __('day.empty_title') }}</div>
            <p class="empty__body">{{ __('day.empty_body') }}</p>
            <a class="btn" href="{{ route('log.photo') }}"><x-icon name="camera" /> {{ __('nav.add_photo') }}</a>
        </div>
    @else
        @foreach ($visibleMeals as $meal)
            @php $rows = $entriesByMeal[$meal->value]; @endphp
            <section class="meal">
                <div class="meal__head">
                    <span class="meal__name">{{ __('meal.'.$meal->value) }}</span>
                    <span class="meal__sub">{{ \App\Support\Format::kcal($rows->sum('kcal')) }} {{ __('nutrition.kcal') }}</span>
                </div>

                @foreach ($rows as $entry)
                    <div class="entry {{ $entry->isVerified() ? '' : 'is-estimate' }}">
                        <div class="entry__body">
                            <div class="entry__name">{{ $entry->name }}</div>
                            <div class="entry__meta">
                                {{ \App\Support\Format::grams($entry->grams) }} {{ __('nutrition.g') }}
                                · <x-prov :source="$entry->source" />
                            </div>
                        </div>
                        <span class="entry__kcal">{{ \App\Support\Format::kcal($entry->kcal) }}</span>
                        <div class="entry__actions">
                            <a class="btn btn--quiet" href="{{ route('entries.edit', $entry) }}">{{ __('common.edit') }}</a>
                            <form method="post" action="{{ route('entries.destroy', $entry) }}"
                                  onsubmit="return confirm('{{ __('common.confirm_delete') }}')">
                                @csrf @method('DELETE')
                                <button class="btn btn--danger-quiet" type="submit">{{ __('common.delete') }}</button>
                            </form>
                        </div>
                    </div>
                @endforeach

                <div class="meal__add">
                    <a class="btn btn--ghost btn--sm" href="{{ route('log.manual', ['meal' => $meal->value]) }}">
                        <x-icon name="plus" /> {{ __('day.add_to_meal') }}
                    </a>
                </div>
            </section>
        @endforeach
    @endif
@endsection
