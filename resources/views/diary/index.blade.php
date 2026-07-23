@extends('layouts.app')

@section('title', $date->isToday() ? __('day.today') : $date->translatedFormat('j F, l'))

@section('content')
    <div class="datestep">
        <a href="{{ route('diary.index', ['date' => $previous->toDateString()]) }}" aria-label="{{ $previous->translatedFormat('j F') }}">‹</a>
        <span class="datestep__label">{{ $date->isToday() ? __('day.today') : $date->translatedFormat('j F, l') }}</span>
        <a href="{{ route('diary.index', ['date' => $next->toDateString()]) }}" aria-label="{{ $next->translatedFormat('j F') }}">›</a>
    </div>

    @if (! $hasAnyEntry)
        <x-empty-state icon="day" :title="__('day.empty_title')" :body="__('day.empty_body')">
            <x-slot:action>
                <x-button href="{{ route('log.photo') }}" icon="camera">{{ __('nav.add_photo') }}</x-button>
                <x-button variant="secondary" href="{{ route('log.manual') }}" icon="manual" data-dialog-open="manual-dialog">{{ __('nav.add_manual') }}</x-button>
            </x-slot:action>
        </x-empty-state>
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
                    <p class="day-estimates prov prov--estimate">{{ __('day.has_estimates') }}</p>
                @endif
            </div>
        </div>
    @endif
@endsection
