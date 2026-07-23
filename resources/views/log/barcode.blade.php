@extends('layouts.app')

@section('title', __('barcode.title'))

@section('content')
    <div class="scan">
        <p class="caption" style="margin-bottom:16px">{{ __('barcode.intro') }}</p>

        @if (session('barcode_status'))
            <p class="notice">{{ session('barcode_status') }}</p>
        @endif

        <form method="post" action="{{ route('log.barcode.lookup') }}" data-barcode-form>
            @csrf

            {{-- Scan: a still frame from the system camera, read in the browser by the
                 native BarcodeDetector. No getUserMedia, no in-page viewfinder, no
                 scanner library. It is enhancement only, hidden until JS confirms the
                 API is present; the code field below always works on its own. --}}
            <div data-barcode-scan hidden style="margin-bottom:16px">
                <label class="flabel" for="frame">{{ __('barcode.scan') }}</label>
                <input type="file" class="field" id="frame" accept="image/*" capture="environment" data-barcode-frame>
                <span class="fhint" data-barcode-scan-hint>{{ __('barcode.scan_hint') }}</span>
                <p class="field-error" data-barcode-unread hidden>{{ __('barcode.unread') }}</p>
            </div>

            {{-- Shown by JS where BarcodeDetector is missing (Firefox, Safari, iOS):
                 say why, plainly, rather than degrade in silence. --}}
            <p class="notice" data-barcode-unsupported hidden>{{ __('barcode.unsupported') }}</p>

            <div class="divider" style="margin-bottom:14px"><div></div><span>{{ __('barcode.code') }}</span><div></div></div>

            <div class="two">
                <div>
                    <label class="visually-hidden" for="code">{{ __('barcode.code') }}</label>
                    <input type="text" class="field" id="code" name="code" inputmode="numeric" pattern="[0-9-]+"
                           value="{{ old('code') }}" required autofocus data-barcode-code
                           placeholder="{{ __('barcode.code_hint') }}">
                </div>
                <x-button type="submit" style="flex:none;min-width:0">{{ __('barcode.lookup') }}</x-button>
            </div>

            @error('code')<p class="field-error">{{ $message }}</p>@enderror
        </form>
    </div>
@endsection
