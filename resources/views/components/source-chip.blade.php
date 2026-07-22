@props(['source'])

{{-- Provenance badge — the core trust signal (hard rule 2). Verified sources read
     as a solid teal-tint pill with a check; USDA carries its own amber identity
     (not a warning); an estimate is a dashed grey ≈, never alarming. Mirrors the
     kit SourceChip. Accepts a NutrientSource or its string value. --}}
@php
    $val = $source instanceof \App\Nutrition\NutrientSource
        ? $source
        : \App\Nutrition\NutrientSource::from($source);

    $isEstimate = $val === \App\Nutrition\NutrientSource::Estimated;
    $isUsda = $val === \App\Nutrition\NutrientSource::Usda;

    $classes = 'source-chip'.($isEstimate ? ' source-chip--estimate' : ($isUsda ? ' source-chip--usda' : ''));
    $glyph = $isEstimate ? '≈' : ($isUsda ? '' : '✓');
@endphp

<span class="{{ $classes }}">@if ($glyph)<span aria-hidden="true">{{ $glyph }}</span> @endif{{ __('source.'.$val->value) }}</span>
