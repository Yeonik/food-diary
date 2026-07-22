@extends('layouts.app')

@section('title', __('photo.title'))

@section('content')
    <h1>{{ __('photo.title') }}</h1>
    <p class="muted">{{ __('photo.privacy') }}</p>
    <p class="muted">{{ __('photo.wait') }}</p>

    <form method="post" action="{{ route('log.photo.store') }}" enctype="multipart/form-data" class="card">
        @csrf
        <div class="field">
            <label for="photo">{{ __('photo.field') }}</label>
            {{-- capture="environment" asks a phone to open the rear camera directly,
                 so the shot gets the system camera's autofocus, exposure and full
                 resolution — meals are photographed close-up in poor light, exactly
                 where those matter, and a custom in-page viewfinder would give up all
                 three. On desktop the attribute is ignored and this stays an ordinary
                 file picker, which is what a desktop user wants. --}}
            <input type="file" name="photo" id="photo" accept="image/*" capture="environment" required>
        </div>
        <button class="btn" type="submit">{{ __('photo.submit') }}</button>
    </form>
@endsection
