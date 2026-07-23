@props(['name', 'checked' => false, 'value' => '1', 'size' => 'md', 'label' => null])

{{-- On/off toggle — teal when on, grey when off, no red/green (design/build,
     .switch). Built on a checkbox rather than the build's button, so it submits
     with the form and toggles without JavaScript. --}}
<label class="switch-field" @if ($label) aria-label="{{ $label }}" @endif>
    <input type="checkbox" class="switch-input" name="{{ $name }}" value="{{ $value }}" @checked($checked) {{ $attributes }}>
    <span class="switch{{ $size === 'sm' ? ' sm' : '' }}"><span></span></span>
</label>
