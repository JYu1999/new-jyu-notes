{{-- 單一 Tweet 媒體項目:image 可開全螢幕 lightbox;sensitive 先模糊、點擊揭露。
     參數:$m(media 項目陣列)、$imgClass(尺寸 class)。 --}}
@php
    $type = $m['type'] ?? 'image';
    $sensitive = ! empty($m['sensitive']);
    $src = media_url($m['path']);
    $alt = $m['alt'] ?? '';
@endphp

<div x-data="{ revealed: {{ $sensitive ? 'false' : 'true' }} }"
    @if($sensitive) :class="!revealed ? 'sensitive-media' : ''" @endif
    class="relative {{ $type === 'image' ? 'tweet-media-clickable' : '' }}"
    @if($type === 'image')
        @click="revealed ? $store.lightbox.show(@js($src), @js($alt)) : (revealed = true)"
    @elseif($sensitive)
        @click="revealed = true"
    @endif>

    @if($type === 'image')
        <img src="{{ $src }}" alt="{{ $alt }}" class="{{ $imgClass }}">
    @else
        <video src="{{ $src }}" :controls="revealed" class="{{ $imgClass }}"></video>
    @endif

    @if($sensitive)
        <div class="sensitive-overlay" x-show="!revealed">
            <span>⚠️ {{ __('public.sensitive_warning') }}</span>
            <span>{{ __('public.sensitive_reveal') }}</span>
        </div>
    @endif
</div>
