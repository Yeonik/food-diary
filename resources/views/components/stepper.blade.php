@props([
    'name',
    'value' => 0,
    'step' => 1,
    'min' => 0,
    'max' => 9999,
    'id' => null,
    'inputmode' => 'numeric',
])

{{-- −/value/+ numeric stepper. Mirrors the kit Stepper, but the middle is a real
     editable input so it works without JS; the buttons are progressive
     enhancement (see public/js/app.js, data-step-*). --}}
@php $id = $id ?? $name; @endphp

<div class="stepper">
    <button type="button" class="stepper__btn" data-step-target="{{ $id }}" data-step-delta="-{{ $step }}" aria-label="−">−</button>
    <input type="number" id="{{ $id }}" name="{{ $name }}" inputmode="{{ $inputmode }}"
           step="{{ $step }}" min="{{ $min }}" max="{{ $max }}" value="{{ $value }}" {{ $attributes }}>
    <button type="button" class="stepper__btn" data-step-target="{{ $id }}" data-step-delta="{{ $step }}" aria-label="+">+</button>
</div>
