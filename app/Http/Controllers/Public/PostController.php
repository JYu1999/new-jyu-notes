<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\PostListRequest;
use App\Repositories\CategoryRepository;
use App\Repositories\PostRepository;
use App\Repositories\TagRepository;
use App\Services\PostService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PostController extends Controller
{
    public function index(
        PostListRequest $request,
        PostRepository $posts,
        CategoryRepository $categories,
        TagRepository $tags,
    ): View {
        $locale = app()->getLocale();
        $params = $request->validated();

        return view('public.posts.index', [
            'posts' => $posts->paginate(
                locale: $locale,
                sort: $params['sort'] ?? 'published',
                tag: $params['tag'] ?? null,
                category: $params['category'] ?? null,
            ),
            'categories' => $categories->all($locale),
            'tags' => $tags->all($locale),
            'sort' => $params['sort'] ?? 'published',
            'selectedTag' => $params['tag'] ?? null,
            'selectedCategory' => $params['category'] ?? null,
        ]);
    }

    public function show(
        string $locale,
        string $slug,
        PostRepository $posts,
        PostService $service,
        Request $request,
    ): View {
        $post = $posts->findPublishedBySlug($locale, $slug);
        abort_if(! $post, 404);

        // Stash the post on the request so TrackPostView middleware can record a view post-response.
        $request->attributes->set('tracked_post', $post);

        $translations = $post->allTranslations()->keyBy('locale');
        // Only published translations are reachable; pass list of locales to layout
        // so the language switcher only shows existing options.
        $availableLocales = $translations
            ->filter(fn ($p) => $p->status === 'published')
            ->keys()
            ->all();

        $backlinks = $post->publishedBacklinks();

        return view('public.posts.show', [
            'post' => $post,
            'translations' => $translations,
            'availableLocales' => $availableLocales,
            'seriesNav' => $service->seriesNavigation($post),
            'backlinks' => $backlinks,
        ]);
    }
}
