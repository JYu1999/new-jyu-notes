<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Page\CreateTranslationRequest;
use App\Http\Requests\Admin\Page\StoreRequest;
use App\Http\Requests\Admin\Page\UpdateRequest;
use App\Models\Page;
use App\Services\PageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PageController extends Controller
{
    public function index(): View
    {
        return view('admin.pages.index', [
            'pages' => Page::query()->withTrashed()->orderByDesc('updated_at')->paginate(50),
        ]);
    }

    public function create(): View
    {
        return view('admin.pages.edit', [
            'page' => new Page(['status' => Page::STATUS_PUBLISHED, 'locale' => app()->getLocale()]),
            'translations' => collect(),
            'mode' => 'create',
        ]);
    }

    public function store(StoreRequest $req, PageService $svc): RedirectResponse
    {
        $page = $svc->create($req->validated());

        return redirect()->route('admin.pages.edit', $page)->with('success', '已建立');
    }

    public function edit(Page $page): View
    {
        return view('admin.pages.edit', [
            'page' => $page,
            'translations' => $page->allTranslations()->keyBy('locale'),
            'mode' => 'edit',
        ]);
    }

    public function update(Page $page, UpdateRequest $req, PageService $svc): RedirectResponse
    {
        $svc->update($page, $req->validated());

        return redirect()->route('admin.pages.edit', $page)->with('success', '已更新');
    }

    public function destroy(Page $page, PageService $svc): RedirectResponse
    {
        $svc->softDelete($page);

        return redirect()->route('admin.pages.index')->with('success', '已移至垃圾桶');
    }

    public function restore(int $id, PageService $svc): RedirectResponse
    {
        $page = Page::withTrashed()->findOrFail($id);
        $svc->restore($page);

        return redirect()->route('admin.pages.index')->with('success', '已還原');
    }

    public function createTranslation(Page $page, CreateTranslationRequest $req, PageService $svc): RedirectResponse
    {
        $new = $svc->createTranslation($page, $req->validated()['locale']);

        return redirect()->route('admin.pages.edit', $new)->with('success', '已建立新翻譯版本');
    }
}
