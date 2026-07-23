@props(['icon', 'title', 'body'])

{{-- Neutral empty placeholder — a soft teal icon tile, a title, a calm
     explanation, an optional action. Never scolds (design/build, .empty).
     Pass the action via <x-slot:action>. --}}
<div class="empty">
    <div class="empty-tile"><x-icon :name="$icon" /></div>
    <h3>{{ $title }}</h3>
    <p>{{ $body }}</p>
    @isset($action)
        <div class="empty-actions">{{ $action }}</div>
    @endisset
</div>
