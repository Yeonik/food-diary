@extends('layouts.app')

@section('title', __('users.title'))

@section('content')
    <div class="invites">
        <h2>{{ __('users.title') }}</h2>
        <p class="s" style="margin-bottom:16px">{{ __('users.intro') }}</p>

        @foreach ($accounts as $account)
            @php $isYou = $account->is(auth()->user()); @endphp

            <x-card style="margin-bottom:10px">
                <div class="account-row">
                    <div>
                        <div class="l">{{ $account->name }}</div>
                        <div class="s">{{ $account->email }}</div>
                        <div class="s">
                            {{ __('users.state.'.($account->isSuspended() ? 'suspended' : 'active')) }}
                            · {{ __('users.joined', ['date' => $account->created_at?->isoFormat('D MMMM YYYY')]) }}
                            @if ($account->isSuspended() && $account->suspended_at)
                                · {{ __('users.suspended_on', ['date' => $account->suspended_at->isoFormat('D MMMM YYYY')]) }}
                            @endif
                        </div>

                        {{-- The owner is listed like everybody else, so the roster
                             is the whole roster. What is missing beside this row
                             is the point: there is nothing here to act on. --}}
                        @if ($account->isOwner())
                            <div class="s">{{ __('users.owner') }}@if ($isYou) · {{ __('users.you') }}@endif</div>
                        @endif
                    </div>

                    {{-- Nothing to press beside your own row. An owner who
                         suspended or deleted themselves would leave an instance
                         nobody can administer, so the action is not offered and
                         the controller refuses it as well. --}}
                    @unless ($isYou)
                        <div style="display:flex; gap:8px; align-items:center">
                            @if ($account->isSuspended())
                                <form method="post" action="{{ route('users.restore', $account) }}">
                                    @csrf @method('DELETE')
                                    <x-button variant="secondary" type="submit">{{ __('users.restore') }}</x-button>
                                </form>
                            @else
                                <form method="post" action="{{ route('users.suspend', $account) }}">
                                    @csrf
                                    <x-button variant="secondary" type="submit">{{ __('users.suspend') }}</x-button>
                                </form>
                            @endif

                            {{-- A link to the confirmation screen, not a delete
                                 button. Nothing is destroyed from a list. --}}
                            <x-button variant="secondary" :href="route('users.delete', $account)">{{ __('users.delete') }}</x-button>
                        </div>
                    @endunless
                </div>
            </x-card>
        @endforeach
    </div>
@endsection
