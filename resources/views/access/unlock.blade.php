@extends('layouts.app')

@section('title', 'Unlock')

@section('content')
    <h1>Unlock</h1>
    <form method="post" action="{{ route('unlock') }}" class="panel">
        @csrf
        <div>
            <label for="password">Access password</label>
            <input type="password" name="password" id="password" autofocus>
        </div>
        <p><button type="submit">Unlock</button></p>
    </form>
@endsection
