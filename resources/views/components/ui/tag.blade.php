{{-- A Tag model as a chip: localized "#name", link to its tag page, tag color tint, optional count. --}}
@props(['tag', 'size' => 'md', 'count' => null, 'locale' => null])

@php
    $loc = $locale ?? app()->getLocale();
    $translations = $tag->relationLoaded('translations') ? $tag->translations : $tag->translations()->get();
    $tt = $translations->firstWhere('locale', $loc);
@endphp

@if($tt)
    <x-ui.chip :href="route('public.tags.show', [$loc, $tt->slug])" :color="$tag->color" :size="$size" {{ $attributes }}>#{{ $tt->name }}@if($count !== null)<span class="ui-chip-count">{{ $count }}</span>@endif</x-ui.chip>
@endif
