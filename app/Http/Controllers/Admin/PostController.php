<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Post\AutosaveRequest;
use App\Http\Requests\Admin\Post\BulkRequest;
use App\Http\Requests\Admin\Post\CreateTranslationRequest;
use App\Http\Requests\Admin\Post\FeaturedOrderRequest;
use App\Http\Requests\Admin\Post\IndexRequest;
use App\Http\Requests\Admin\Post\StoreRequest;
use App\Http\Requests\Admin\Post\UpdateRequest;
use App\Http\Requests\Admin\Post\UpdateStatusRequest;
use App\Models\Post;
use App\Repositories\AdminPostListRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\TagRepository;
use App\Services\FeaturedOrderService;
use App\Services\PostService;
use App\Services\TranslationStatusService;
use App\Support\MarkdownRenderer;
use App\Support\TocBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PostController extends Controller
{
    public function index(
        IndexRequest $request,
        AdminPostListRepository $list,
        CategoryRepository $categories,
        TagRepository $tags,
    ): View {
        $filters = $request->filters();

        // "精選" tab: the ordered featured list (JYU-547).
        if ($filters['status'] === 'featured') {
            return view('admin.posts.index', [
                'featuredList' => app(FeaturedOrderService::class)->articles(),
                'counts' => $list->counts(),
                'filters' => $filters,
                'categories' => $categories->all(),
                'tags' => $tags->all(),
            ]);
        }

        $posts = $list->paginate($filters);

        $data = [
            'posts' => $posts,
            'languageStates' => $filters['status'] === 'trashed' ? [] : $list->languageStates($posts->getCollection()),
            'counts' => $list->counts(),
            'filters' => $filters,
            'categories' => $categories->all(),
            'tags' => $tags->all(),
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
            'versionStates' => [],
            'cites' => collect(),
            'citedBy' => collect(),
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

    public function edit(Post $post, TagRepository $tags, CategoryRepository $categories, TranslationStatusService $status): View
    {
        $post->load(['tags.translations', 'categories.translations']);
        $translations = $post->allTranslations()->keyBy('locale');
        $staleIds = $status->stale()->where('posts.post_group_id', $post->post_group_id)->pluck('posts.id')->flip();
        $originalId = $translations->min('id');

        return view('admin.posts.edit', [
            'post' => $post,
            'translations' => $translations,
            // Every language version's state, for the header and sidebar (JYU-548).
            'versionStates' => collect(TranslationStatusService::LOCALES)->mapWithKeys(fn ($loc) => [$loc => [
                'post' => $translations[$loc] ?? null,
                'original' => isset($translations[$loc]) && $translations[$loc]->id === $originalId,
                'stale' => isset($translations[$loc]) && isset($staleIds[$translations[$loc]->id]),
            ]])->all(),
            'cites' => $post->outgoingReferences()->with('target')->get()->pluck('target')->filter()->unique(fn ($m) => $m::class.$m->id)->values(),
            'citedBy' => $post->incomingReferences()->with('source')->get()->pluck('source')->filter()->unique(fn ($m) => $m::class.$m->id)->values(),
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

    /** Bulk actions from the post list (JYU-546). */
    public function bulk(BulkRequest $request, PostService $service): RedirectResponse
    {
        $posts = Post::query()->whereIn('id', $request->validated('ids'))->get();
        $message = $service->bulk($request->validated('action'), $posts, $request->validated('tag_id'));

        return back()->with('success', $message);
    }

    /** Save the featured order after a drag (JYU-547). */
    public function featuredOrder(FeaturedOrderRequest $request, FeaturedOrderService $featured): JsonResponse
    {
        $featured->reorder($request->validated('group_ids'));

        return response()->json(['ok' => true]);
    }

    /**
     * Editor autosave (JYU-548). Drafts are saved in place (a new post becomes a
     * draft on first save). Published / hidden posts are never autosaved — their
     * changes would go live, so the editor asks for an explicit ⌘S instead.
     */
    public function autosave(AutosaveRequest $request, PostService $service): JsonResponse
    {
        $data = collect($request->validated())->except('post_id')->all();
        $post = $request->filled('post_id') ? Post::findOrFail($request->validated('post_id')) : null;

        if ($post && $post->status !== Post::STATUS_DRAFT) {
            return response()->json(['saved' => false, 'reason' => 'not_draft'], 409);
        }

        if (blank($data['title'] ?? null) && blank($data['body'] ?? null)) {
            return response()->json(['saved' => false, 'reason' => 'empty'], 422);
        }

        if ($post) {
            $post = $service->update($post, array_filter($data, fn ($v) => $v !== null) + ['excerpt' => $data['excerpt'] ?? null]);
        } else {
            $post = $service->create([
                ...$data,
                'title' => filled($data['title'] ?? null) ? $data['title'] : '未命名草稿',
                'body' => $data['body'] ?? '',
                'status' => Post::STATUS_DRAFT,
            ]);
        }

        return response()->json([
            'saved' => true,
            'id' => $post->id,
            'saved_at' => now()->toIso8601String(),
            'update_url' => route('admin.posts.update', $post),
            'edit_url' => route('admin.posts.edit', $post),
            'slug' => $post->slug,
        ]);
    }

    /** Rendered preview with the same Markdown pipeline as the public post page (JYU-548). */
    public function preview(Request $request): JsonResponse
    {
        $request->validate(['body' => 'nullable|string']);
        $html = app(MarkdownRenderer::class)->render((string) $request->input('body', ''));

        return response()->json(['html' => TocBuilder::build($html)['html']]);
    }
}
