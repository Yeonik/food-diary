@extends('layouts.app')

@section('title', __('users.delete_title', ['name' => $account->name]))

@section('content')
    <div class="invites">
        <h2>{{ __('users.delete_title', ['name' => $account->name]) }}</h2>
        <p class="s" style="margin-bottom:16px">{{ __('users.delete_explain') }}</p>

        {{-- What would go, counted from the same list that does the deleting.
             The point of the screen: the owner reads what this account actually
             holds before deciding, rather than a generic warning. --}}
        <x-card style="margin-bottom:16px">
            <div class="flabel" style="margin-bottom:6px">{{ $account->email }}</div>
            @forelse ($tally as $table => $count)
                <div class="s">{{ __('users.holds.'.$table, ['count' => $count]) }}</div>
            @empty
                <div class="s">{{ __('users.delete_nothing') }}</div>
            @endforelse
        </x-card>

        <form method="post" action="{{ route('users.destroy', $account) }}">
            @csrf @method('DELETE')

            {{-- The address, typed. Not the owner's password: that would only
                 prove the owner is present, which the session already says. This
                 proves they mean this account and not the row above it. --}}
            <x-field name="email" :label="__('users.delete_confirm_label', ['email' => $account->email])"
                     autocomplete="off" autocapitalize="none" required autofocus />

            <div style="display:flex; gap:10px; margin-top:14px">
                <x-button variant="danger" type="submit">{{ __('users.delete_submit') }}</x-button>
                <x-button variant="secondary" :href="route('users.index')">{{ __('common.cancel') }}</x-button>
            </div>
        </form>
    </div>
@endsection
