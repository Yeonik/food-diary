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
                <x-switch name="goal_enabled" :checked="$enabled" :label="__('settings.goal')" data-dim-toggle="goal-card" />
            </x-card>

            {{-- The target card, visibly quieter when no goal is set: the diary
                 works either way, and saying so is the point of the dimming, not
                 decoration. Its fields stay editable, so turning the goal back on
                 finds the numbers where they were left. The app never suggests
                 lowering a target — these are fields, and nothing else. --}}
            <x-card id="goal-card" :dim="! $enabled" style="margin-bottom:16px">
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

        {{-- The account. Nothing to configure here yet beyond the password — the
             address is what you signed in with, and there is no profile. --}}
        <x-card style="margin-bottom:16px">
            <div class="account-row">
                <div>
                    <div class="l">{{ __('auth.account') }}</div>
                    <div class="who">{{ auth()->user()?->email }}</div>
                </div>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <x-button variant="secondary" type="submit">{{ __('auth.logout') }}</x-button>
                </form>
            </div>

            <form method="post" action="{{ route('user-password.update') }}"
                  style="border-top:1px solid var(--border);margin-top:16px;padding-top:16px">
                @csrf @method('PUT')
                <div class="flabel" style="margin-bottom:10px">{{ __('auth.change_password') }}</div>

                <x-field type="password" name="current_password" :label="__('auth.current_password')"
                         autocomplete="current-password" required />
                <x-field type="password" name="password" :label="__('auth.new_password')"
                         autocomplete="new-password" required :hint="__('auth.password_hint')" />
                <x-field type="password" name="password_confirmation" :label="__('auth.password_confirm')"
                         autocomplete="new-password" required />

                {{-- Fortify validates this form into its own error bag, so its
                     messages do not appear in the layout's shared list. --}}
                @foreach ($errors->updatePassword->all() as $message)
                    <p class="field-error">{{ $message }}</p>
                @endforeach

                <x-button type="submit" style="margin-top:14px">{{ __('auth.change_password_submit') }}</x-button>
            </form>
        </x-card>

        {{-- Leaving. Plain, and last on the screen: it asks for the password
             because it cannot be undone, not because it is discouraged. --}}
        @unless (auth()->user()?->isOwner())
            <x-card style="margin-bottom:16px">
                <form method="post" action="{{ route('account.destroy') }}">
                    @csrf @method('DELETE')
                    <div class="flabel" style="margin-bottom:6px">{{ __('account.delete') }}</div>
                    <p class="s" style="margin-bottom:12px">{{ __('account.delete_explain') }}</p>

                    <x-field type="password" name="current_password" :label="__('auth.current_password')"
                             autocomplete="current-password" required />

                    <x-button variant="danger" type="submit">{{ __('account.delete_submit') }}</x-button>
                </form>
            </x-card>
        @endunless

        {{-- Only the owner has anywhere to go from here, and only the owner sees
             it. The link is a convenience; the gate on the route is the rule. --}}
        @can(\App\Providers\AppServiceProvider::ADMINISTER_INVITES)
            <x-card class="set-row" style="margin-bottom:16px">
                <span>
                    <span class="t">{{ __('invites.settings_link') }}</span>
                    <span class="s" style="display:block">{{ __('invites.settings_hint') }}</span>
                </span>
                <x-button variant="secondary" :href="route('invites.index')">{{ __('invites.open') }}</x-button>
            </x-card>
        @endcan

        @can(\App\Providers\AppServiceProvider::ADMINISTER_ACCOUNTS)
            <x-card class="set-row" style="margin-bottom:16px">
                <span>
                    <span class="t">{{ __('users.settings_link') }}</span>
                    <span class="s" style="display:block">{{ __('users.settings_hint') }}</span>
                </span>
                <x-button variant="secondary" :href="route('users.index')">{{ __('users.open') }}</x-button>
            </x-card>
        @endcan

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
