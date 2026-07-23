@extends('layouts.app')

@section('title', __('auth.register_title'))

@section('content')
    {{-- design/build, .auth, dark icon variant. The invite field and the privacy
         notice arrive with the invite gate; until then this screen is reachable
         only where registration is enabled, and it creates an ordinary account. --}}
    <div class="auth">
        <div class="auth-icon dark"><x-icon name="user-plus" width="28" height="28" /></div>
        <h2>{{ __('auth.register_title') }}</h2>
        <p class="auth-sub">{{ __('auth.register_sub') }} <a href="{{ route('login') }}">{{ __('auth.login_title') }}</a></p>

        <form method="post" action="{{ route('register') }}">
            @csrf
            <x-field name="name" :label="__('auth.name')" :value="old('name')"
                     autocomplete="name" required autofocus />
            <x-field type="email" name="email" :label="__('auth.email')" :value="old('email')"
                     autocomplete="username" inputmode="email" required />
            <x-field type="password" name="password" :label="__('auth.password')"
                     autocomplete="new-password" required :hint="__('auth.password_hint')" />
            <x-field type="password" name="password_confirmation" :label="__('auth.password_confirm')"
                     autocomplete="new-password" required />

            <x-button type="submit" full style="margin-top:22px">{{ __('auth.register_submit') }}</x-button>
        </form>
    </div>
@endsection
