@extends('layouts.app')

@section('title', 'Log manually')

@section('content')
    <h1>Log manually</h1>
    <p class="muted">Search your library, USDA and Open Food Facts by name.</p>

    <form method="post" action="{{ route('log.manual.store') }}" class="panel">
        @csrf
        <div class="row">
            <div style="flex:1">
                <label for="name">Food name</label>
                <input type="text" name="name" id="name" value="{{ old('name') }}" style="width:100%" required autofocus>
            </div>
            <div><button type="submit">Search</button></div>
        </div>
    </form>
@endsection
