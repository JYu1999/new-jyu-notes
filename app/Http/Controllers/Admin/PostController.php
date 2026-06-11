<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Post\CreateTranslationRequest;
use App\Http\Requests\Admin\Post\IndexRequest;
use App\Http\Requests\Admin\Post\StoreRequest;
use App\Http\Requests\Admin\Post\UpdateRequest;
use App\Http\Requests\Admin\Post\UpdateStatusRequest;
use App\Models\Post;
use App\Repositories\CategoryRepository;
use App\Repositories\PostRepository;
use App\Repositories\TagRepository;
use App\Services\PostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PostController extends Controller
{
    public function index(IndexRequest $request, PostRepository $repo): View
    {
        $params = $request->validated();
        $data = [
            'posts' => $repo->adminPaginate(
                status: $params['status'] ?? null,
                locale: $params['locale'] ?? null,
                search: $params['q'] ?? null,
            ),
            'counts' => $repo->countsByStatus(),
            'currentStatus' => $params['status'] ?? 'all',
            'currentLocale' => $params['locale'] ?? null,
            'currentSearch' => $params['q'] ?? '',
        ];

        // AJAX live-filter: return only the table partial
        if ($request->boolean('partial')) {
            return view('admin.posts._table', $data);
        }

        return view('admin.posts.index', $data);
    }

    public function create(TagRepository $tags, CategoryRepository $categories): View
    {
        return view('admin.posts.edit', [
            'post' => new Post([
                'status' => Post::STATUS_DRAFT,
                'locale' => app()->getLocale(),
                'is_featured' => false,
            ]),
            'translations' => collect(),
            'tags' => $tags->all(),
            'categories' => $categories->all(),
            'mode' => 'create',
        ]);
    }

    public function store(StoreRequest $request, PostService $service): RedirectResponse
    {
        $post = $service->create($request->validated());
        return redirect()
            ->route('admin.posts.edit', $post)
            ->with('success', '已建立');
    }

    public function edit(Post $post, TagRepository $tags, CategoryRepository $categories): View
    {
        $post->load(['tags.translations', 'categories.translations']);

        return view('admin.posts.edit', [
            'post' => $post,
            'translations' => $post->allTranslations()->keyBy('locale'),
            'tags' => $tags->all(),
            'categories' => $categories->all(),
            'mode' => 'edit',
        ]);
    }

    public function update(Post $post, UpdateRequest $request, PostService $service): RedirectResponse
    {
        $service->update($post, $request->validated());
        return redirect()->route('admin.posts.edit', $post)->with('success', '已更新');
    }

    public function destroy(Post $post, PostService $service): RedirectResponse
    {
        $service->softDelete($post);
        return redirect()->route('admin.posts.index')->with('success', '已移至垃圾桶');
    }

    public function restore(int $id, PostService $service): RedirectResponse
    {
        $post = Post::withTrashed()->findOrFail($id);
        $service->restore($post);
        return redirect()->route('admin.posts.index')->with('success', '已還原');
    }

    public function updateStatus(Post $post, UpdateStatusRequest $request, PostService $service): RedirectResponse
    {
        $service->updateStatus($post, $request->validated()['status']);
        return back()->with('success', '狀態已更新');
    }

    public function createTranslation(Post $post, CreateTranslationRequest $request, PostService $service): RedirectResponse
    {
        $new = $service->createTranslation($post, $request->validated()['locale']);
        return redirect()->route('admin.posts.edit', $new)
            ->with('success', '已建立新翻譯版本');
    }

    public function search(Request $request, PostRepository $repo): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json([]);
        }

        $locale = (string) $request->query('locale', app()->getLocale());
        $exclude = $request->integer('exclude');

        $results = $repo->searchForMention($q, $locale, $exclude > 0 ? $exclude : null);

        return response()->json(
            $results->map(fn (Post $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'slug' => $p->slug,
                'locale' => $p->locale,
                'url' => "/{$p->locale}/posts/{$p->slug}",
            ])->all()
        );
    }
}
