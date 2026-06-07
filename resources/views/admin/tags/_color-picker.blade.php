{{-- Color picker: preset swatches + native custom picker + clear. Submits via hidden input name="color". --}}
@php
    $presets = [
        '#b2543b' => '陶土紅',
        '#a83a2e' => '緋紅',
        '#c4783a' => '橙',
        '#b08234' => '赭黃',
        '#6f8a4a' => '草綠',
        '#4a6b3f' => '墨綠',
        '#4a7a6f' => '青瓷',
        '#3f5e7a' => '靛藍',
        '#7a5a8a' => '紫藤',
        '#8a6f4a' => '棕褐',
    ];
@endphp
<div x-data="{
        color: @js((string) ($value ?? '')),
        presets: @js(array_keys($presets)),
        get isCustom() { return this.color !== '' && !this.presets.includes(this.color) },
    }" class="flex items-center gap-1.5 flex-wrap">
    <input type="hidden" name="color" :value="color">
    @foreach($presets as $hex => $label)
        <button type="button" @click="color = '{{ $hex }}'" title="{{ $label }} {{ $hex }}"
            class="w-6 h-6 rounded-full border border-line transition-transform hover:scale-110"
            :class="color === '{{ $hex }}' && 'ring-2 ring-accent ring-offset-2 ring-offset-card scale-110'"
            style="background: {{ $hex }}"></button>
    @endforeach
    {{-- custom color via native picker --}}
    <label class="relative w-6 h-6 rounded-full border border-dashed border-line-2 cursor-pointer inline-flex items-center justify-center text-[11px] leading-none"
        :class="isCustom && 'ring-2 ring-accent ring-offset-2 ring-offset-card'"
        :style="isCustom ? `background:${color}` : ''" title="自訂顏色">
        <span x-show="!isCustom">🎨</span>
        <input type="color" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
            :value="color || '#b2543b'" @input="color = $event.target.value">
    </label>
    <span x-show="color" x-text="color" x-cloak class="text-xs font-mono text-ink-3 ml-1"></span>
    <button type="button" @click="color = ''" x-show="color" x-cloak
        class="text-xs text-ink-3 hover:text-danger" title="清除顏色">✕ 清除</button>
</div>
