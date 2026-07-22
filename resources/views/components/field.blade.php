@props([
    'label' => null,
    'hint' => null,
    'name' => null,
    'type' => 'text',
    'id' => null,
    'value' => null,
])

{{-- Labelled text/number field. Mirrors the kit Input. The <input> keeps working
     without JavaScript; any extra attributes pass straight through. --}}
@php $id = $id ?? $name; @endphp

<label class="field">
    @if ($label)<span class="field__label">{{ $label }}</span>@endif
    <input type="{{ $type }}"
           @if ($name) name="{{ $name }}" @endif
           @if ($id) id="{{ $id }}" @endif
           @if (! is_null($value)) value="{{ $value }}" @endif
           {{ $attributes }}>
    @if ($hint)<span class="field__hint">{{ $hint }}</span>@endif
</label>
