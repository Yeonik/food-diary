@extends('layouts.app')

@section('title', $date->isToday() ? __('day.today') : $date->translatedFormat('j F, l'))

{{-- The day is the one screen that moves between dates, so its arrows sit in the
     topbar beside the title, which already names the day being shown. --}}
@section('topbar_nav')
    <div class="date-nav">
        <a class="back" href="{{ route('diary.index', ['date' => $previous->toDateString()]) }}"
           aria-label="{{ $previous->translatedFormat('j F') }}">‹</a>
        <a class="back" href="{{ route('diary.index', ['date' => $next->toDateString()]) }}"
           aria-label="{{ $next->translatedFormat('j F') }}">›</a>
    </div>
@endsection

@section('content')
    @if (! $hasAnyEntry)
        {{-- No buttons here: the three ways to log something are in the topbar and
             the FAB, which stay put whether or not the day has entries. --}}
        <x-empty-state icon="day" :title="__('day.empty_title')" :body="__('day.empty_body')" />
    @else
        <div class="day">
            {{-- Meals: a card each, in a responsive grid. On desktop this column
                 sits left of the summary; on mobile it drops below it. --}}
            <div class="day-meals">
                <div class="meals-grid">
                    @foreach ($visibleMeals as $meal)
                        @php $rows = $entriesByMeal[$meal->value]; @endphp
                        <div class="meal">
                            <div class="meal-head">
                                <div>
                                    <span class="meal-title">{{ __('meal.'.$meal->value) }}</span>
                                    <span class="meal-kcal">{{ \App\Support\Format::kcal($rows->sum(fn ($e) => round($e->kcal))) }} {{ __('nutrition.kcal') }}</span>
                                </div>
                                <a class="add" href="{{ route('log.manual', ['meal' => $meal->value]) }}"
                                   data-dialog-open="manual-dialog" aria-label="{{ __('day.add_to_meal') }}">+</a>
                            </div>

                            @foreach ($rows as $entry)
                                {{-- The row reveals its edit / delete icons in the kcal's
                                     place, as the build does — but as a native <details>,
                                     so the reveal works with JavaScript off. --}}
                                <details class="rec">
                                    <summary class="rec-btn">
                                        <div class="rec-main">
                                            <div class="rec-name">{{ $entry->name }}</div>
                                            <div class="rec-meta">
                                                <span class="rec-g">{{ \App\Support\Format::grams($entry->grams) }} {{ __('nutrition.g') }}</span>
                                                <x-source-chip :source="$entry->source" />
                                            </div>
                                        </div>
                                        <div class="rec-k">{{ \App\Support\Format::kcal($entry->kcal) }}</div>
                                    </summary>
                                    <div class="rec-actions">
                                        <x-icon-button href="{{ route('entries.edit', $entry) }}" :label="__('common.edit')" icon="edit" />
                                        <form method="post" action="{{ route('entries.destroy', $entry) }}"
                                              onsubmit="return confirm('{{ __('common.confirm_delete') }}')">
                                            @csrf @method('DELETE')
                                            <x-icon-button type="submit" tone="danger" :label="__('common.delete')" icon="delete" />
                                        </form>
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Summary: the ring (or the plain eaten number), then the three macro
                 cards. Sticky beside the meals on desktop, on top on mobile. --}}
            <div class="ring-card">
                <x-ring-summary :eaten="$summary->kcal" :goal="$goal?->daily_kcal" />

                <div class="macros">
                    <x-macro-row kind="protein" :label="__('nutrition.protein')" :value="$summary->proteinG" :goal="$goal?->protein_g" />
                    <x-macro-row kind="fat" :label="__('nutrition.fat')" :value="$summary->fatG" :goal="$goal?->fat_g" />
                    <x-macro-row kind="carb" :label="__('nutrition.carbs')" :value="$summary->carbsG" :goal="$goal?->carbs_g" />
                </div>

                @if ($summary->hasEstimates)
                    {{-- Some of today's numbers are the model's estimates. The
                         totals say so plainly; they are not quietly counted as
                         verified (hard rule 2). --}}
                    <div class="zero-note">{{ __('day.has_estimates') }}</div>
                @endif
            </div>
        </div>
    @endif
@endsection
