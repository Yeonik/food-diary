@extends('layouts.app')

@section('title', __('access.title'))

@section('content')
    {{-- The shared-secret gate. Sign-in, registration and password reset are
         v0.3.0; this is the one screen that uses the build's .auth block. --}}
    <div class="auth">
        <div class="auth-icon"><x-icon name="lock" width="28" height="28" /></div>
        <h2>{{ __('access.title') }}</h2>

        <form method="post" action="{{ route('unlock') }}" style="margin-top:24px">
            @csrf
            <x-field type="password" name="password" :label="__('access.password')" autofocus />
            <x-button type="submit" full style="margin-top:22px">{{ __('access.submit') }}</x-button>
        </form>
    </div>
@endsection
