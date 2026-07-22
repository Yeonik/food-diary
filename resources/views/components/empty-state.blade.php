@props(['icon', 'title', 'body'])

{{-- Neutral empty placeholder — a soft teal icon tile, a title, a calm
     explanation, an optional action. Never scolds. Mirrors the kit EmptyState.
     Pass the action via <x-slot:action>. --}}
<div class="empty-state">
    <div class="empty-state__tile"><x-icon :name="$icon" /></div>
    <div class="empty-state__title">{{ $title }}</div>
    <p class="empty-state__body">{{ $body }}</p>
    @isset($action)
        <div class="empty-state__action">{{ $action }}</div>
    @endisset
</div>
