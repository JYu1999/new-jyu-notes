<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Category\StoreRequest;
use App\Http\Requests\Admin\Category\UpdateRequest;
use App\Models\Category;
use App\Repositories\CategoryRepository;
use App\Services\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(CategoryRepository $repo): View
    {
        return view('admin.categories.index', [
            'categories' => $repo->all(),
        ]);
    }

    public function store(StoreRequest $request, CategoryService $service): RedirectResponse
    {
        $service->create($request->validated());
        return back()->with('success', '已建立');
    }

    public function update(Category $category, UpdateRequest $request, CategoryService $service): RedirectResponse
    {
        $service->update($category, $request->validated());
        return back()->with('success', '已更新');
    }

    public function destroy(Category $category, CategoryService $service): RedirectResponse
    {
        $service->delete($category);
        return back()->with('success', '已刪除');
    }
}
