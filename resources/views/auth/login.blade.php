@extends('layouts.app')

@section('title', __('auth.login_title'))

@section('content')
    {{-- design/build, .auth. No "forgot password?" link: there is no reset to
         link to, and a link to nothing is worse than its absence. --}}
    <div class="auth">
        <div class="auth-icon"><x-icon name="lock" width="28" height="28" /></div>
        <h2>{{ __('auth.login_title') }}</h2>
        <p class="auth-sub">{{ __('auth.login_sub') }}</p>

        <form method="post" action="{{ route('login') }}">
            @csrf
            <x-field type="email" name="email" :label="__('auth.email')" :value="old('email')"
                     autocomplete="username" inputmode="email" required autofocus />
            <x-field type="password" name="password" :label="__('auth.password')"
                     autocomplete="current-password" required />

            <label class="remember">
                <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                <span>{{ __('auth.remember') }}</span>
            </label>

            <x-button type="submit" full style="margin-top:22px">{{ __('auth.login_submit') }}</x-button>
        </form>
    </div>
@endsection
