@props(['name', 'checked' => false, 'value' => '1'])

{{-- On/off toggle — teal when on, grey when off, no red/green. Mirrors the kit
     Switch, but built on a checkbox so it submits and toggles without JS. --}}
<label class="switch">
    <input type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked($checked) {{ $attributes }}>
    <span class="switch__slider"></span>
</label>
