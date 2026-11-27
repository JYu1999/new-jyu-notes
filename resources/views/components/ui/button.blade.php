{{--
    Button / link-button.
      variant → 'secondary' (outlined, default) | 'primary' (ink fill) | 'ghost' (no border)
      size    → 'md' (44px, default touch target) | 'sm' (36px) | 'lg' (48px, full-width "load more")
      icon    → square icon button (needs aria-label)
      pill    → fully rounded
      href    → renders <a>
      type    → button type (default "button")
--}}
@props(['variant' => 'secondary', 'size' => 'md', 'icon' => false, 'pill' => false, 'href' => null, 'type' => 'button'])

@php
    $variant = in_array($variant, ['primary', 'secondary', 'ghost'], true) ? $variant : 'secondary';
    $size = in_array($size, ['sm', 'md', 'lg'], true) ? $size : 'md';
    $classes = ['ui-btn', "ui-btn-{$variant}", "ui-btn-{$size}", 'ui-btn-icon' => $icon, 'ui-btn-pill' => $pill];
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
