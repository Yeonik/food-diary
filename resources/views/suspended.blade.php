@extends('layouts.app')

@section('title', __('account.suspended_title'))

@section('content')
    {{-- The build's .auth block, like the sign-in screen: one centred card and
         no rail, because there is nowhere to navigate to. --}}
    <div class="auth">
        <div class="auth-icon"><x-icon name="lock" width="28" height="28" /></div>
        <h2>{{ __('account.suspended_title') }}</h2>

        {{-- Says what has happened and what has not. No apology, no appeal
             process invented here, and no suggestion that anything was done
             wrong: the owner suspends an account for reasons the application
             does not know. --}}
        <p class="auth-sub">{{ __('account.suspended_body') }}</p>

        @if ($since)
            <p class="s" style="margin-top:12px">
                {{ __('account.suspended_since', ['date' => $since->isoFormat('D MMMM YYYY')]) }}
            </p>
        @endif

        <form method="post" action="{{ route('logout') }}" style="margin-top:22px">
            @csrf
            <x-button variant="secondary" type="submit" full>{{ __('auth.logout') }}</x-button>
        </form>
    </div>
@endsection
