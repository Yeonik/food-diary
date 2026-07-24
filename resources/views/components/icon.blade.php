@props(['name', 'stroke' => '1.8'])

{{-- Inline SVG icons, one unified set. The paths are the ones in
     design/build/index.html, transcribed as they are — 24×24 box, stroked in
     currentColor, rounded joins. No icon font, no sprite fetch. --}}
@php
    $paths = [
        // Primary navigation (also reused, larger and tinted, in empty states).
        'day' => '<rect x="3" y="4" width="18" height="17" rx="3"/><path d="M3 9h18M8 2v4M16 2v4"/>',
        'history' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'weight' => '<rect x="3" y="4" width="18" height="17" rx="4"/><path d="M9 9l3-3 3 3"/>',
        'library' => '<path d="M5 4h13a1 1 0 0 1 1 1v15H6a1 1 0 0 1-1-1V4z"/><path d="M9 4v16"/>',
        'goal' => '<path d="M4 7h10M18 7h2M4 17h2M10 17h10"/><circle cx="16" cy="7" r="2.4"/><circle cx="8" cy="17" r="2.4"/>',

        // Actions.
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'camera' => '<path d="M4 9h3l1.5-2.5h7L17 9h3a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-9a1 1 0 0 1 1-1z"/><circle cx="11.5" cy="14.5" r="3.3"/>',
        'barcode' => '<rect x="3" y="6" width="18" height="12" rx="1.5"/><path d="M6 9v6M9 9v6M12 9v6M15 9v6M18 9v6"/>',
        'manual' => '<path d="M4 20h4L18 10l-4-4L4 16v4z"/><path d="M13 5l4 4"/>',
        'edit' => '<path d="M4 20h4L18 10l-4-4L4 16v4z"/><path d="M13 5l4 4"/>',
        'delete' => '<path d="M5 7h14M9 7V5h6v2M7 7l1 13h8l1-13"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M16.2 16.2L21 21"/>',
        'lock' => '<rect x="4" y="10" width="16" height="11" rx="2.5"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/><circle cx="12" cy="15.5" r="1.6" fill="currentColor"/>',
        'user-plus' => '<circle cx="10" cy="8" r="3.5"/><path d="M4 20c0-3.3 2.7-6 6-6 1.3 0 2.5.4 3.5 1.1"/><path d="M18 14v6M15 17h6"/>',
        // The accounts already here, as against inviting one more. Same head and
        // shoulders as user-plus, with a second person behind rather than a plus.
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><path d="M16.5 5a3.5 3.5 0 0 1 0 6.6"/><path d="M18.5 14.4c1.5 1 2.5 2.7 2.5 4.6"/>',
        'utensils' => '<path d="M3 2v7c0 1.1.9 2 2 2a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-3 4.5V15a2 2 0 0 0 4 0Z"/>',
    ];
@endphp

<svg {{ $attributes->merge(['aria-hidden' => 'true']) }} viewBox="0 0 24 24" fill="none"
     stroke="currentColor" stroke-width="{{ $stroke }}" stroke-linecap="round" stroke-linejoin="round">
    {!! $paths[$name] ?? '' !!}
</svg>
