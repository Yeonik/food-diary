@props(['name', 'checked' => false, 'value' => '1', 'size' => 'md', 'label' => null])

{{-- On/off toggle — teal when on, grey when off, no red/green (design/build,
     .switch). Built on a checkbox rather than the build's button, so it submits
     with the form and toggles without JavaScript. --}}
{{-- The label wraps the control and carries no text of its own, so the name goes
     on the checkbox: an aria-label on the <label> would not name the input. --}}
<label class="switch-field">
    <input type="checkbox" class="switch-input" name="{{ $name }}" value="{{ $value }}"
           @if ($label) aria-label="{{ $label }}" @endif @checked($checked) {{ $attributes }}>
    <span class="switch{{ $size === 'sm' ? ' sm' : '' }}"><span></span></span>
</label>
