<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Repositories\PostRepository;
use App\Repositories\TagRepository;
use App\Repositories\TweetRepository;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(PostRepository $posts, TweetRepository $tweets, TagRepository $tags): View
    {
        $locale = app()->getLocale();

        return view('public.home', [
            'featuredPosts' => $posts->featured($locale, 4),
            'recentTweets' => $tweets->recent($locale, 4),
            'popularTags' => $tags->popular($locale, 12),
        ]);
    }
}
