@extends('layouts.admin')

@section('title', 'Media')

@section('content')
<header class="mb-6 flex items-center justify-between gap-3">
    <div>
        <h1 class="font-serif text-2xl font-semibold">Media</h1>
        <p class="text-sm text-ink-3 mt-1">{{ $media->total() }} 個檔案</p>
    </div>
    <label class="bg-accent text-white px-4 py-2 rounded-md hover:bg-accent-ink text-sm font-medium cursor-pointer">
        + 上傳檔案
        <input type="file" class="hidden" id="upload-input" accept="image/*,video/*">
    </label>
</header>

<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
    @forelse($media as $m)
        <div class="bg-card border border-line rounded-md overflow-hidden group">
            @if(str_starts_with($m->mime_type, 'image/'))
                <img src="{{ $m->url() }}" alt="{{ $m->original_filename }}" class="w-full aspect-video object-cover">
            @elseif(str_starts_with($m->mime_type, 'video/'))
                <video src="{{ $m->url() }}" class="w-full aspect-video object-cover" muted></video>
            @else
                <div class="aspect-video bg-paper-2 flex items-center justify-center text-ink-3">file</div>
            @endif
            <div class="p-3 text-xs">
                <div class="font-mono truncate text-ink-2">{{ $m->original_filename }}</div>
                <div class="text-ink-3 mt-1">{{ number_format($m->size / 1024, 1) }} KB</div>
                <div class="mt-2 flex justify-between">
                    <button onclick="navigator.clipboard.writeText('{{ $m->path }}')" class="text-accent hover:text-accent-ink">複製路徑</button>
                    <form method="POST" action="{{ route('admin.media.destroy', $m->id) }}" class="inline" onsubmit="return confirm('刪除？')">
                        @csrf @method('DELETE')
                        <button class="text-danger hover:underline">刪除</button>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <p class="col-span-full text-center py-12 text-ink-3">尚無檔案</p>
    @endforelse
</div>

<div class="mt-6">{{ $media->links() }}</div>

<script>
document.getElementById('upload-input')?.addEventListener('change', async (e) => {
    const file = e.target.files[0];
    if (!file) return;
    const fd = new FormData();
    fd.append('file', file);
    const res = await fetch('{{ route('admin.media.store') }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
        body: fd
    });
    if (res.ok) location.reload();
    else alert('上傳失敗');
});
</script>
@endsection
