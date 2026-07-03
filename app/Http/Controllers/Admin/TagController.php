<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Tag\StoreRequest;
use App\Http\Requests\Admin\Tag\UpdateRequest;
use App\Models\Tag;
use App\Repositories\TagRepository;
use App\Services\TagService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TagController extends Controller
{
    public function index(TagRepository $repo): View
    {
        return view('admin.tags.index', [
            'tags' => $repo->allWithCounts(),
        ]);
    }

    public function store(StoreRequest $request, TagService $service): RedirectResponse
    {
        $service->create($request->validated());

        return back()->with('success', '已建立');
    }

    public function update(Tag $tag, UpdateRequest $request, TagService $service): RedirectResponse
    {
        $service->update($tag, $request->validated());

        return back()->with('success', '已更新');
    }

    public function destroy(Tag $tag, TagService $service): RedirectResponse
    {
        $service->delete($tag);

        return back()->with('success', '已刪除');
    }
}
