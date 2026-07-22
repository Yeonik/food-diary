@props(['name'])

{{-- Inline SVG icons, stroked in currentColor. No icon font, no sprite fetch. --}}
@php
    $paths = [
        'day' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18M8 2v4M16 2v4"/>',
        'history' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'weight' => '<circle cx="12" cy="5" r="2"/><path d="M8 7h8l3 13H5z"/>',
        'library' => '<path d="M4 4h13a2 2 0 0 1 2 2v14H6a2 2 0 0 1-2-2z"/><path d="M4 18a2 2 0 0 1 2-2h13"/>',
        'goal' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.5"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'camera' => '<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>',
        'manual' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'utensils' => '<path d="M3 2v7c0 1.1.9 2 2 2a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-3 4.5V15a2 2 0 0 0 4 0Z"/>',
    ];
@endphp

<svg {{ $attributes->merge(['aria-hidden' => 'true']) }} viewBox="0 0 24 24" fill="none"
     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    {!! $paths[$name] ?? '' !!}
</svg>
