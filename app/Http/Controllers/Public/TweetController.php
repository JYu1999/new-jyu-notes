<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Repositories\TweetRepository;
use Illuminate\View\View;

class TweetController extends Controller
{
    public function index(TweetRepository $tweets): View
    {
        return view('public.tweets.index', [
            'tweets' => $tweets->paginate(app()->getLocale(), 20),
        ]);
    }

    public function show(string $locale, int $id, TweetRepository $tweets): View
    {
        $tweet = $tweets->findPublished($locale, $id);
        abort_if(! $tweet, 404);

        return view('public.tweets.show', [
            'tweet' => $tweet,
        ]);
    }
}
