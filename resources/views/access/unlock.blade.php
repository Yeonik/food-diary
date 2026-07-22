@extends('layouts.app')

@section('title', __('access.title'))

@section('content')
    <h1>{{ __('access.title') }}</h1>
    <form method="post" action="{{ route('unlock') }}" class="panel">
        @csrf
        <div>
            <label for="password">{{ __('access.password') }}</label>
            <input type="password" name="password" id="password" autofocus>
        </div>
        <p><button type="submit">{{ __('access.submit') }}</button></p>
    </form>
@endsection
