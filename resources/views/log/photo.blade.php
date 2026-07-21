@extends('layouts.app')

@section('title', 'Log a photo')

@section('content')
    <h1>Log a photo</h1>
    <p class="muted">
        The photo is sent to Google's Gemini API for recognition. Its EXIF metadata
        (including GPS) is stripped before it leaves this machine, and the file is
        deleted once you confirm the entry.
    </p>
    <p class="muted">
        Recognition usually takes a few seconds. When the model is busy it retries,
        so it can take up to two minutes — the page will wait, that is not an error.
    </p>

    <form method="post" action="{{ route('log.photo.store') }}" enctype="multipart/form-data" class="panel">
        @csrf
        <div>
            <label for="photo">Meal photo</label>
            <input type="file" name="photo" id="photo" accept="image/*" required>
        </div>
        <p><button type="submit">Recognise</button></p>
    </form>
@endsection
