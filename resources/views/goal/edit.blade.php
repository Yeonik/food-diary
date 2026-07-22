@extends('layouts.app')

@section('title', __('settings.title'))

@section('content')
    <h1>{{ __('settings.title') }}</h1>

    @php $enabled = (bool) old('goal_enabled', $goal?->daily_kcal !== null); @endphp

    <form method="post" action="{{ route('goal.update') }}">
        @csrf @method('PATCH')

        {{-- Goal card — visibly dims when the goal is off, stating plainly that
             the diary works without one. --}}
        <div class="card {{ $enabled ? '' : 'card--dim' }}" data-dim>
            <div class="setting-row">
                <div>
                    <div class="setting-title">{{ __('settings.goal') }}</div>
                    <div class="caption">{{ __('settings.goal_hint') }}</div>
                </div>
                <label class="switch">
                    <input type="checkbox" name="goal_enabled" value="1" data-dim-toggle @checked($enabled)>
                    <span class="switch__slider"></span>
                </label>
            </div>

            <div class="field">
                <label for="daily_kcal">{{ __('settings.daily_kcal') }}</label>
                <div class="stepper">
                    <button type="button" class="stepper__btn" data-step-target="daily_kcal" data-step-delta="-50" aria-label="−">−</button>
                    <input type="number" id="daily_kcal" name="daily_kcal" inputmode="numeric" step="50" min="0" max="6000"
                           value="{{ old('daily_kcal', $goal?->daily_kcal ?? 2000) }}">
                    <button type="button" class="stepper__btn" data-step-target="daily_kcal" data-step-delta="50" aria-label="+">+</button>
                </div>
            </div>

            <div class="section-title"><h2>{{ __('settings.macros') }}</h2></div>
            @foreach (['protein_g' => 'protein', 'fat_g' => 'fat', 'carbs_g' => 'carbs'] as $field => $key)
                <div class="field">
                    <label for="{{ $field }}">{{ __('nutrition.'.$key) }}, {{ __('nutrition.g') }}</label>
                    <div class="stepper">
                        <button type="button" class="stepper__btn" data-step-target="{{ $field }}" data-step-delta="-5" aria-label="−">−</button>
                        <input type="number" id="{{ $field }}" name="{{ $field }}" inputmode="numeric" step="5" min="0" max="600"
                               value="{{ old($field, $goal?->{$field}) }}">
                        <button type="button" class="stepper__btn" data-step-target="{{ $field }}" data-step-delta="5" aria-label="+">+</button>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Meal visibility toggles --}}
        @php
            $meals = ['breakfast', 'lunch', 'dinner', 'snack'];
            $shownCount = collect($meals)->filter(fn (string $m): bool => (bool) old('show_'.$m, $goal?->{'show_'.$m} ?? true))->count();
        @endphp
        <div class="card">
            <div class="setting-row">
                <div>
                    <div class="setting-title">{{ __('settings.meals') }}</div>
                    <div class="caption">{{ __('settings.meals_hint') }}</div>
                </div>
                <span class="caption">{{ __('settings.meals_count', ['on' => $shownCount]) }}</span>
            </div>

            @foreach ($meals as $m)
                <label class="switch switch--row">
                    <span class="switch__label">{{ __('meal.'.$m) }}</span>
                    <input type="checkbox" name="show_{{ $m }}" value="1" @checked(old('show_'.$m, $goal?->{'show_'.$m} ?? true))>
                    <span class="switch__slider"></span>
                </label>
            @endforeach
        </div>

        <button class="btn" type="submit">{{ __('settings.save') }}</button>
    </form>

    {{-- Language. Two submit buttons post the choice; it is saved in a cookie
         and the redirect back re-renders at once. Works without JavaScript. --}}
    <div class="card">
        <div class="setting-title">{{ __('settings.language') }}</div>
        <form method="post" action="{{ route('locale.update') }}" class="segmented" role="group" aria-label="{{ __('settings.language') }}">
            @csrf
            <button type="submit" name="locale" value="ru" class="segmented__btn {{ app()->getLocale() === 'ru' ? 'is-active' : '' }}">RU</button>
            <button type="submit" name="locale" value="en" class="segmented__btn {{ app()->getLocale() === 'en' ? 'is-active' : '' }}">EN</button>
        </form>
    </div>
@endsection
