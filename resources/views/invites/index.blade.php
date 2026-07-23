@extends('layouts.app')

@section('title', __('invites.title'))

@section('content')
    <div class="invites">
        <h2>{{ __('invites.title') }}</h2>
        <p class="s" style="margin-bottom:16px">{{ __('invites.intro') }}</p>

        {{-- The code, once. It came through the session from the request that
             created it and is not stored anywhere, so this is the only time it
             can be read. Refreshing loses it, which is why the screen says so. --}}
        @if (session('issued_code'))
            <x-card style="margin-bottom:16px">
                <div class="flabel">{{ __('invites.issued') }}</div>
                <p class="issued-code">{{ session('issued_code') }}</p>
                <p class="s">{{ __('invites.issued_once') }}</p>
            </x-card>
        @endif

        <form method="post" action="{{ route('invites.store') }}" style="margin-bottom:16px">
            @csrf
            <x-button type="submit">{{ __('invites.issue') }}</x-button>
        </form>

        {{-- The load on the shared key today, as one number. Not per person:
             the owner pays for the key, which is a different thing from being
             able to watch what anybody is eating. --}}
        <x-card class="set-row" style="margin-bottom:16px">
            <span>
                <span class="t">{{ __('invites.recognitions_today') }}</span>
                <span class="s" style="display:block">{{ __('invites.recognitions_hint', ['limit' => $dailyLimit]) }}</span>
            </span>
            <span class="usage-total">{{ $recognisedToday }}</span>
        </x-card>

        @forelse ($invites as $invite)
            <x-card style="margin-bottom:10px">
                <div class="account-row">
                    <div>
                        <div class="l">{{ __('invites.state.'.$invite->state()) }}</div>
                        <div class="s">
                            {{ __('invites.issued_on', ['date' => $invite->created_at?->isoFormat('D MMMM YYYY')]) }}
                            @if ($invite->redeemer)
                                · {{ $invite->redeemer->email }}
                            @endif
                        </div>
                    </div>

                    @if ($invite->state() === 'open')
                        <form method="post" action="{{ route('invites.destroy', $invite) }}">
                            @csrf @method('DELETE')
                            <x-button variant="secondary" type="submit">{{ __('invites.revoke') }}</x-button>
                        </form>
                    @endif
                </div>
            </x-card>
        @empty
            <x-empty-state icon="user-plus" :title="__('invites.none')" :body="__('invites.none_body')" />
        @endforelse
    </div>
@endsection
