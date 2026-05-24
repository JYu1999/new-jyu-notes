<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Repositories\PostRepository;
use App\Repositories\TagRepository;
use App\Repositories\TweetRepository;
use Illuminate\View\View;

class TagController extends Controller
{
    public function show(
        string $locale,
        string $slug,
        TagRepository $tags,
        PostRepository $posts,
        TweetRepository $tweets,
    ): View {
        $tag = $tags->findBySlug($locale, $slug);
        abort_if(! $tag, 404);

        return view('public.tags.show', [
            'tag' => $tag,
            'posts' => $posts->byTag($tag, $locale, 12),
            'tweets' => $tweets->byTag($tag, $locale, 20),
        ]);
    }
}
