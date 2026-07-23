@extends('layouts.app')

@section('title', __('manual.title'))

@section('content')
    <div class="narrow520 vform">
        <p class="caption">{{ __('manual.intro') }}</p>

        <x-card>
            <form method="post" action="{{ route('log.manual.store') }}" class="vform">
                @csrf
                <x-field name="name" :label="__('manual.name')" :value="old('name')" required autofocus />
                <x-button type="submit">{{ __('manual.search') }}</x-button>
            </form>
        </x-card>
    </div>
@endsection
