<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Tweet;
use App\Repositories\PostRepository;
use App\Repositories\TweetRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MentionController extends Controller
{
    public function search(Request $request, PostRepository $posts, TweetRepository $tweets): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $locale = (string) $request->query('locale', app()->getLocale());
        $excludeType = (string) $request->query('exclude_type', '');
        $excludeId = $request->integer('exclude_id');

        $excludePost = $excludeType === 'post' && $excludeId > 0 ? $excludeId : null;
        $excludeTweet = $excludeType === 'tweet' && $excludeId > 0 ? $excludeId : null;

        $postResults = $posts->searchForMention($q, $locale, $excludePost, 6)
            ->map(fn (Post $p) => [
                'type' => 'post',
                'id' => $p->id,
                'label' => $p->title,
                'url' => "/{$p->locale}/posts/{$p->slug}",
            ]);

        $tweetResults = $tweets->searchForMention($q, $locale, $excludeTweet, 6)
            ->map(fn (Tweet $t) => [
                'type' => 'tweet',
                'id' => $t->id,
                'label' => $t->preview(60),
                'url' => "/{$t->locale}/tweets/{$t->id}",
            ]);

        return response()->json($postResults->concat($tweetResults)->values()->all());
    }
}
