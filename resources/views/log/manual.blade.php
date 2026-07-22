@extends('layouts.app')

@section('title', __('manual.title'))

@section('content')
    <h1>{{ __('manual.title') }}</h1>
    <p class="muted">{{ __('manual.intro') }}</p>

    <form method="post" action="{{ route('log.manual.store') }}" class="card">
        @csrf
        <div class="field">
            <label for="name">{{ __('manual.name') }}</label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" required autofocus>
        </div>
        <button class="btn" type="submit">{{ __('manual.search') }}</button>
    </form>
@endsection
