{{-- Tweet media: 1 → full, 2 → side-by-side, 3+ → horizontal scroll-snap. $media = tweet media array. --}}
@php $media = is_array($media ?? null) ? array_values($media) : []; @endphp
@if(count($media) === 1)
    <div class="mt-3">
        @include('components.partials.tweet-media', ['m' => $media[0], 'imgClass' => 'rounded-md w-full h-auto object-cover max-h-96'])
    </div>
@elseif(count($media) === 2)
    <div class="mt-3 grid grid-cols-2 gap-2">
        @foreach($media as $m)
            @include('components.partials.tweet-media', ['m' => $m, 'imgClass' => 'rounded-md w-full h-48 object-cover'])
        @endforeach
    </div>
@elseif(count($media) > 2)
    <div class="mt-3 -mx-1 flex gap-2 overflow-x-auto snap-x snap-mandatory pb-2 scrollbar-thin">
        @foreach($media as $m)
            <div class="snap-start flex-shrink-0 w-56 sm:w-64">
                @include('components.partials.tweet-media', ['m' => $m, 'imgClass' => 'rounded-md w-full h-44 object-cover'])
            </div>
        @endforeach
    </div>
@endif

