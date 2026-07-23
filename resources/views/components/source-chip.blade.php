@props(['source'])

{{-- Provenance badge — the core trust signal (hard rule 2). Verified sources read
     as a solid teal-tint pill with a check; USDA carries its own amber identity,
     which is that source's colour and NOT a warning; an estimate is a dashed grey
     ≈, lighter and never alarming (design/build, .chip / .chip.usda / .dash).

     Takes a NutrientSource (which source answered), a ProfileOrigin (where a
     stored library item first came from) or a plain value — they share the
     source.* labels, so the badge reads the same wherever provenance is shown. --}}
@php
    $val = $source instanceof \BackedEnum ? (string) $source->value : (string) $source;

    $isEstimate = $val === \App\Nutrition\NutrientSource::Estimated->value;
    $isUsda = $val === \App\Nutrition\NutrientSource::Usda->value;

    $classes = $isEstimate ? 'dash' : ($isUsda ? 'chip usda' : 'chip');
    $glyph = $isEstimate ? '≈' : ($isUsda ? '' : '✓');
@endphp

<span class="{{ $classes }}">@if ($glyph)<span aria-hidden="true">{{ $glyph }}</span>@endif{{ __('source.'.$val) }}</span>
