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

            {{-- The capture panel. It is a label for a file input, not a live
                 viewfinder: the shot is taken by the system camera, which focuses
                 and exposes better than anything we could put on the page, and the
                 browser reads the barcode off that one still frame. It is hidden
                 until JavaScript confirms BarcodeDetector exists — where it does
                 not, the number field below is the whole feature. --}}
            <div data-barcode-scan hidden>
                <label class="scan-view">
                    <input type="file" class="visually-hidden" accept="image/*"
                           capture="environment" data-barcode-frame>
                    <span class="visually-hidden">{{ __('barcode.scan') }}</span>
                    <span class="scan-frame" aria-hidden="true">
                        <span></span><span></span><span></span><span></span>
                    </span>
                    {{-- Decorative: a barcode, so the panel reads as one at a glance. --}}
                    <svg width="150" height="66" viewBox="0 0 150 66" aria-hidden="true" style="opacity:.85">
                        <g fill="#fff">
                            <rect x="6" y="8" width="4" height="50"/><rect x="14" y="8" width="2" height="50"/>
                            <rect x="20" y="8" width="6" height="50"/><rect x="30" y="8" width="3" height="50"/>
                            <rect x="37" y="8" width="5" height="50"/><rect x="46" y="8" width="2" height="50"/>
                            <rect x="52" y="8" width="7" height="50"/><rect x="63" y="8" width="3" height="50"/>
                            <rect x="70" y="8" width="4" height="50"/><rect x="78" y="8" width="6" height="50"/>
                            <rect x="88" y="8" width="2" height="50"/><rect x="94" y="8" width="5" height="50"/>
                            <rect x="103" y="8" width="3" height="50"/><rect x="110" y="8" width="6" height="50"/>
                            <rect x="120" y="8" width="2" height="50"/><rect x="126" y="8" width="4" height="50"/>
                            <rect x="134" y="8" width="6" height="50"/>
                        </g>
                    </svg>
                </label>
                <p class="scan-hint">{{ __('barcode.scan_hint') }}</p>
                {{-- A frame that could not be read. Says that, and only that. --}}
                <p class="field-error" data-barcode-unread hidden>{{ __('barcode.unread') }}</p>
            </div>

            {{-- Shown by JS where BarcodeDetector is missing (Firefox, Safari, iOS).
                 It names the cause — the browser cannot scan — rather than blaming
                 the photo, which is a different message entirely. --}}
            <p class="notice" data-barcode-unsupported hidden>{{ __('barcode.unsupported') }}</p>

            <div class="divider" style="margin-bottom:14px">
                <div></div><span>{{ __('barcode.code') }}</span><div></div>
            </div>

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
