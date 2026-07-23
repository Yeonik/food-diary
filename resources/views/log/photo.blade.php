@extends('layouts.app')

@section('title', __('photo.title'))

@section('content')
    <div class="narrow520 vform">
        <p class="caption">{{ __('photo.privacy') }}</p>
        <p class="caption">{{ __('photo.wait') }}</p>

        <x-card>
            <form method="post" action="{{ route('log.photo.store') }}" enctype="multipart/form-data" class="vform">
                @csrf
                <div>
                    <label class="flabel" for="photo">{{ __('photo.field') }}</label>
                    {{-- capture="environment" asks a phone to open the rear camera directly,
                         so the shot gets the system camera's autofocus, exposure and full
                         resolution — meals are photographed close-up in poor light, exactly
                         where those matter, and a custom in-page viewfinder would give up all
                         three. On desktop the attribute is ignored and this stays an ordinary
                         file picker, which is what a desktop user wants. --}}
                    <input type="file" class="field" name="photo" id="photo" accept="image/*" capture="environment" required>
                </div>
                <x-button type="submit">{{ __('photo.submit') }}</x-button>
            </form>
        </x-card>
    </div>
@endsection
