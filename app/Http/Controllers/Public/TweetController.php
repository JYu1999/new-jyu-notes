<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Repositories\TweetRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TweetController extends Controller
{
    public function index(TweetRepository $tweets, Request $request): View
    {
        $paginator = $tweets->paginate(app()->getLocale(), 20);

        // Infinite-scroll AJAX request: return only the items partial
        if ($request->boolean('partial')) {
            return view('public.tweets._items', ['tweets' => $paginator]);
        }

        return view('public.tweets.index', ['tweets' => $paginator]);
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
