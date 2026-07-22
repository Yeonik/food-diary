@extends('layouts.app')

@section('title', __('barcode.title'))

@section('content')
    <h1>{{ __('barcode.title') }}</h1>
    <p class="muted">{{ __('barcode.intro') }}</p>

    @if (session('barcode_status'))
        <p class="notice">{{ session('barcode_status') }}</p>
    @endif

    <form method="post" action="{{ route('log.barcode.lookup') }}" class="card" data-barcode-form>
        @csrf

        {{-- Scan: a still frame from the system camera, read in the browser by the
             native BarcodeDetector. No getUserMedia, no in-page viewfinder, no
             scanner library. It is enhancement only, hidden until JS confirms the
             API is present; the code field below always works on its own. --}}
        <div class="field" data-barcode-scan hidden>
            <label for="frame">{{ __('barcode.scan') }}</label>
            <input type="file" id="frame" accept="image/*" capture="environment" data-barcode-frame>
            <p class="caption" data-barcode-scan-hint>{{ __('barcode.scan_hint') }}</p>
            <p class="field-error" data-barcode-unread hidden>{{ __('barcode.unread') }}</p>
        </div>

        {{-- Shown by JS where BarcodeDetector is missing (Firefox, Safari, iOS):
             say why, plainly, rather than degrade in silence. --}}
        <p class="notice" data-barcode-unsupported hidden>{{ __('barcode.unsupported') }}</p>

        <div class="field">
            <label for="code">{{ __('barcode.code') }}</label>
            <input type="text" id="code" name="code" inputmode="numeric" pattern="[0-9-]+"
                   value="{{ old('code') }}" required autofocus data-barcode-code>
            <p class="caption">{{ __('barcode.code_hint') }}</p>
        </div>

        @error('code')<p class="field-error">{{ $message }}</p>@enderror

        <button class="btn btn--block" type="submit">{{ __('barcode.lookup') }}</button>
    </form>
@endsection
