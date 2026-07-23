@extends('layouts.app')

@section('title', __('settings.title'))

@section('content')
    @php $enabled = (bool) old('goal_enabled', $goal?->daily_kcal !== null); @endphp

    <div class="settings">
        <form method="post" action="{{ route('goal.update') }}">
            @csrf @method('PATCH')

            <x-card class="set-row" style="margin-bottom:16px">
                <div>
                    <div class="t">{{ __('settings.goal') }}</div>
                    <div class="s">{{ __('settings.goal_hint') }}</div>
                </div>
                <x-switch name="goal_enabled" :checked="$enabled" :label="__('settings.goal')" data-dim-toggle />
            </x-card>

            {{-- The target card, visibly quieter when no goal is set — the diary
                 works either way, and never suggests lowering a target. --}}
            <x-card :dim="! $enabled" data-dim style="margin-bottom:16px">
                <div class="flabel" style="margin-bottom:10px">{{ __('settings.daily_kcal') }}</div>
                <x-stepper name="daily_kcal" :value="old('daily_kcal', $goal?->daily_kcal ?? 2000)"
                           step="50" min="0" max="6000" :unit="__('nutrition.kcal')" />

                <div style="border-top:1px solid var(--border);margin-top:18px;padding-top:16px">
                    <div class="flabel" style="margin-bottom:12px">
                        {{ __('settings.macros') }} · <span class="opt">{{ __('common.optional') }}</span>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:10px">
                        @foreach (['protein_g' => 'protein', 'fat_g' => 'fat', 'carbs_g' => 'carbs'] as $field => $key)
                            <div class="macro-goal">
                                <label class="l" for="{{ $field }}">{{ __('nutrition.'.$key) }}</label>
                                <x-stepper size="sm" :name="$field" :value="old($field, $goal?->{$field})"
                                           step="5" min="0" max="600" :unit="__('nutrition.g')" />
                            </div>
                        @endforeach
                    </div>
                </div>
            </x-card>

            {{-- Meal visibility --}}
            @php
                $meals = ['breakfast', 'lunch', 'dinner', 'snack'];
                $shown = fn (string $m): bool => (bool) old('show_'.$m, $goal?->{'show_'.$m} ?? true);
                $shownCount = collect($meals)->filter($shown)->count();
            @endphp
            <x-card style="margin-bottom:16px">
                <div class="set-row">
                    <span class="t">{{ __('settings.meals') }}</span>
                    <span class="s">{{ __('settings.meals_count', ['on' => $shownCount]) }}</span>
                </div>
                <div class="s" style="margin-top:2px">{{ __('settings.meals_hint') }}</div>
                @foreach ($meals as $m)
                    <div class="meal-toggle {{ $shown($m) ? '' : 'off' }}">
                        <span class="t">{{ __('meal.'.$m) }}</span>
                        <x-switch size="sm" name="show_{{ $m }}" :checked="$shown($m)" :label="__('meal.'.$m)" />
                    </div>
                @endforeach
            </x-card>

            <x-button type="submit">{{ __('settings.save') }}</x-button>
        </form>

        {{-- Language. Two submit buttons post the choice; it is saved in a cookie
             and the redirect back re-renders at once. Works without JavaScript. --}}
        <x-card class="set-row">
            <span class="t">{{ __('settings.language') }}</span>
            <form method="post" action="{{ route('locale.update') }}" class="lang" aria-label="{{ __('settings.language') }}">
                @csrf
                <button type="submit" name="locale" value="ru" class="{{ app()->getLocale() === 'ru' ? 'on' : '' }}">RU</button>
                <button type="submit" name="locale" value="en" class="{{ app()->getLocale() === 'en' ? 'on' : '' }}">EN</button>
            </form>
        </x-card>
    </div>
@endsection
