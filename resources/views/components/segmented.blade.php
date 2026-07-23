@props(['label' => null])

{{-- Segmented switch — period (week / month / range), language RU/EN
     (design/build, .seg). A presentational container: the caller fills it with
     links for GET or submit buttons for POST and marks the current one `on`, so
     the control works without JavaScript. --}}
<div {{ $attributes->merge(['class' => 'seg']) }} role="group" @if ($label) aria-label="{{ $label }}" @endif>
    {{ $slot }}
</div>
