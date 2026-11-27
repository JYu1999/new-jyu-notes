{{--
    Surface card (bg-card, hairline border, 12px radius).
      href     → renders <a>, with hover state
      as       → element when not a link (div | article | section | li | aside)
      padding  → 'none' | 'sm' (16px) | 'md' (18px, default) | 'lg' (32px)
      radius   → 'md' (12px, default) | 'lg' (14px, featured cards)
--}}
@props(['href' => null, 'as' => 'div', 'padding' => 'md', 'radius' => 'md'])

@php
    $el = $href ? 'a' : (in_array($as, ['div', 'article', 'section', 'li', 'aside'], true) ? $as : 'div');
    $padding = in_array($padding, ['none', 'sm', 'md', 'lg'], true) ? $padding : 'md';
    $classes = ['ui-card', "ui-card-pad-{$padding}", 'ui-card-lg' => $radius === 'lg', 'ui-card-interactive' => (bool) $href];
@endphp

<{{ $el }} {{ $attributes->merge($href ? ['href' => $href] : [])->class($classes) }}>{{ $slot }}</{{ $el }}>
