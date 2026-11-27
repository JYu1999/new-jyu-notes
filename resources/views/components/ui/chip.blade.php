{{--
    Pill chip — tags, filters, small labels.
      href     → renders <a>
      as       → 'span' (default without href) | 'button' (filter toggle)
      color    → #rrggbb tag tint (falls back to neutral when absent/invalid)
      size     → 'md' (13px, default) | 'sm' (12px, inline meta rows)
      pressed  → filter-toggle state (as="button" only), sets aria-pressed
--}}
@props(['href' => null, 'as' => null, 'color' => null, 'size' => 'md', 'pressed' => null])

@php
    $tag = $href ? 'a' : ($as ?? 'span');
    $tint = is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : null;
    $classes = ['ui-chip', 'ui-chip-' . ($size === 'sm' ? 'sm' : 'md')];
    if ($tag === 'button' && $pressed !== null) {
        $classes[] = 'ui-chip-toggle';
        if ($pressed) {
            $classes[] = 'ui-chip-pressed';
        }
    }
    if (! in_array('ui-chip-pressed', $classes, true)) {
        $classes[] = $tint ? 'tag-chip' : 'ui-chip-neutral';
    }
@endphp

@php
    $extra = [];
    if ($href) { $extra['href'] = $href; }
    if ($tag === 'button') { $extra['type'] = 'button'; }
    if ($tag === 'button' && $pressed !== null) { $extra['aria-pressed'] = $pressed ? 'true' : 'false'; }
    $style = $tint && ! in_array('ui-chip-pressed', $classes, true) ? "--tag-color: {$tint}" : null;
@endphp
<{{ $tag }} {{ $attributes->merge($extra)->class($classes) }}@if($style) style="{{ $style }}"@endif>{{ $slot }}</{{ $tag }}>
