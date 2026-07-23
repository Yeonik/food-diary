@props([
    'name',
    'value' => 0,
    'step' => 1,
    'min' => 0,
    'max' => 9999,
    'id' => null,
    'unit' => null,        // shown after the number (kcal, g)
    'size' => 'lg',        // lg — the daily target; sm — a macro target row
    'inputmode' => 'numeric',
])

{{-- −/value/+ numeric stepper (design/build: .stepper for the daily target,
     .mg-ctl for a macro row). The middle is a real editable input so it works
     without JavaScript; the buttons are progressive enhancement (see
     public/js/app.js, data-step-*). --}}
@php $id = $id ?? $name; @endphp

@if ($size === 'sm')
    <div class="mg-ctl">
        <button type="button" class="mg-btn" data-step-target="{{ $id }}" data-step-delta="-{{ $step }}" aria-label="−">−</button>
        <div class="mg-val">
            <input type="number" id="{{ $id }}" name="{{ $name }}" inputmode="{{ $inputmode }}"
                   step="{{ $step }}" min="{{ $min }}" max="{{ $max }}" value="{{ $value }}" {{ $attributes }}>@if ($unit) {{ $unit }}@endif
        </div>
        <button type="button" class="mg-btn" data-step-target="{{ $id }}" data-step-delta="{{ $step }}" aria-label="+">+</button>
    </div>
@else
    <div class="stepper">
        <button type="button" class="step-btn" data-step-target="{{ $id }}" data-step-delta="-{{ $step }}" aria-label="−">−</button>
        <div class="step-num">
            <input type="number" id="{{ $id }}" name="{{ $name }}" inputmode="{{ $inputmode }}"
                   step="{{ $step }}" min="{{ $min }}" max="{{ $max }}" value="{{ $value }}" {{ $attributes }}>@if ($unit)<small>{{ $unit }}</small>@endif
        </div>
        <button type="button" class="step-btn" data-step-target="{{ $id }}" data-step-delta="{{ $step }}" aria-label="+">+</button>
    </div>
@endif
