<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Tweet;
use App\Repositories\PostRepository;
use App\Repositories\TweetRepository;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(PostRepository $posts, TweetRepository $tweets): View
    {
        return view('admin.dashboard', [
            'stats' => [
                'posts_published' => $posts->countByStatus(Post::STATUS_PUBLISHED),
                'posts_draft' => $posts->countByStatus(Post::STATUS_DRAFT),
                'tweets_published' => $tweets->countByStatus(Tweet::STATUS_PUBLISHED),
                'tweets_draft' => $tweets->countByStatus(Tweet::STATUS_DRAFT),
            ],
            'recentPosts' => $posts->recentForAdmin(5),
            'recentTweets' => $tweets->recentForAdmin(5),
        ]);
    }
}
