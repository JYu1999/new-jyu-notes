<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\TweetListRequest;
use App\Repositories\TagRepository;
use App\Repositories\TweetRepository;
use Illuminate\View\View;

class TweetController extends Controller
{
    public function index(TweetListRequest $request, TweetRepository $tweets, TagRepository $tags): View
    {
        $locale = app()->getLocale();
        $filters = $request->filters();

        $tag = null;
        if ($filters['tag'] !== null) {
            $tag = $tags->findBySlug($locale, $filters['tag']) ?? false;
        }

        $paginator = $tweets->paginateFiltered($locale, $tag, $filters['month'], $filters['media'], 20);

        // "Load older" request: only the next page of items.
        if ($request->boolean('partial')) {
            return view('public.tweets._items', ['tweets' => $paginator]);
        }

        return view('public.tweets.index', [
            'tweets' => $paginator,
            'filters' => $filters,
            'tags' => $tags->forTweets($locale),
            'months' => $tweets->monthCounts($locale),
        ]);
    }

    public function show(string $locale, int $id, TweetRepository $tweets): View
    {
        $tweet = $tweets->findPublished($locale, $id);
        abort_if(! $tweet, 404);

        $availableLocales = $tweet->allTranslations()
            ->filter(fn ($t) => $t->status === 'published')
            ->pluck('locale')
            ->all();

        return view('public.tweets.show', [
            'tweet' => $tweet,
            'availableLocales' => $availableLocales,
            'backlinks' => $tweet->publishedBacklinks(),
        ]);
    }
}
