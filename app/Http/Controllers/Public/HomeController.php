<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\HomeRequest;
use App\Repositories\CategoryRepository;
use App\Repositories\PostRepository;
use App\Repositories\TagRepository;
use App\Services\ChangelogService;
use App\Services\HomeFeedService;
use App\Support\PublicNav;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class HomeController extends Controller
{
    public const FEED_PAGE_SIZE = 10;

    public const FEATURED_ON_HOME = 3;

    public function index(
        HomeRequest $request,
        HomeFeedService $feed,
        PostRepository $posts,
        TagRepository $tags,
        CategoryRepository $categories,
        ChangelogService $changelog,
    ): View|JsonResponse {
        $locale = app()->getLocale();
        $type = $request->validated('type') ?? 'all';
        $page = $feed->latest($locale, $type, $request->validated('before'), self::FEED_PAGE_SIZE);

        // "Load more" / tab switch: just the timeline items.
        if ($request->boolean('partial')) {
            return response()->json([
                'html' => view('public.home._feed-items', ['items' => $page['items']])->render(),
                'next' => $page['next'],
            ]);
        }

        $featured = $posts->featuredForHome($locale, self::FEATURED_ON_HOME);

        return view('public.home', [
            'feedType' => $type,
            'feedItems' => $page['items'],
            'feedNext' => $page['next'],
            'featured' => $featured['items'],
            'featuredTotal' => $featured['total'],
            'topics' => $tags->popular($locale, 5),
            'series' => $categories->all($locale)
                ->filter(fn ($c) => $c->posts_count > 0)
                ->sortByDesc('posts_count')
                ->take(5)
                ->values(),
            'tags' => $tags->popular($locale, 12),
            'latestChange' => $changelog->latest(),
            'workUrl' => PublicNav::aboutUrl($locale),
        ]);
    }
}
