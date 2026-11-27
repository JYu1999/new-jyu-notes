{{--
    Section heading.
      size        → 'lg' (26px serif, page sections) | 'md' (20px serif, sidebars) | 'label' (13px caps-style label)
      as          → heading element (default h2)
      action-href / action-label → optional link on the right ("查看全部 →")
--}}
@props(['as' => 'h2', 'size' => 'lg', 'actionHref' => null, 'actionLabel' => null])

@php
    $size = in_array($size, ['lg', 'md', 'label'], true) ? $size : 'lg';
    $el = in_array($as, ['h1', 'h2', 'h3', 'h4', 'div'], true) ? $as : 'h2';
@endphp

@if($actionHref)
    <div {{ $attributes->except('id')->class('ui-heading-row') }}>
        <{{ $el }} {{ $attributes->only('id')->class(['ui-heading', "ui-heading-{$size}"]) }}>{{ $slot }}</{{ $el }}>
        <a href="{{ $actionHref }}" class="ui-heading-action">{{ $actionLabel }}</a>
    </div>
@else
    <{{ $el }} {{ $attributes->class(['ui-heading', "ui-heading-{$size}"]) }}>{{ $slot }}</{{ $el }}>
@endif
