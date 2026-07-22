@props(['label' => null])

{{-- Pill segmented switch — period (Неделя/Месяц/Диапазон), language RU/EN.
     Mirrors the kit SegmentedControl. A presentational container: the caller
     fills it with .segmented__btn items (links for GET, submits for POST) and
     marks the current one `is-active`, so the control works without JS. --}}
<div {{ $attributes->merge(['class' => 'segmented']) }} role="group" @if ($label) aria-label="{{ $label }}" @endif>
    {{ $slot }}
</div>
