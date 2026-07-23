@props([
    'label' => null,
    'hint' => null,
    'name' => null,
    'type' => 'text',
    'id' => null,
    'value' => null,
])

{{-- A labelled control. In design/build the class sits on the control itself,
     with .flabel above and .fhint below — not on a wrapper. The <input> keeps
     working without JavaScript; extra attributes pass straight through. --}}
@php $id = $id ?? $name; @endphp

<div>
    @if ($label)<label class="flabel" @if ($id) for="{{ $id }}" @endif>{{ $label }}</label>@endif
    <input type="{{ $type }}" class="field"
           @if ($name) name="{{ $name }}" @endif
           @if ($id) id="{{ $id }}" @endif
           @if (! is_null($value)) value="{{ $value }}" @endif
           {{ $attributes }}>
    @if ($hint)<span class="fhint">{{ $hint }}</span>@endif
</div>
