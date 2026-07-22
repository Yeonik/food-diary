@props(['source'])

{{-- Provenance glyph (hard rule 2): a check for a verified source, an
     approximation sign for the model's estimate — which reads visibly lighter. --}}
@php $verified = $source->isVerified(); @endphp

<span class="prov {{ $verified ? 'prov--verified' : 'prov--estimate' }}">
    <span class="prov__glyph" aria-hidden="true">{{ $verified ? '✓' : '≈' }}</span>
    {{ __('source.'.$source->value) }}
</span>
