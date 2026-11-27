<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Repositories\CategoryRepository;
use App\Repositories\TagRepository;
use Illuminate\View\View;

class TopicController extends Controller
{
    public function index(CategoryRepository $categories, TagRepository $tags): View
    {
        $locale = app()->getLocale();

        return view('public.topics.index', [
            'categories' => $categories->all($locale)->sortByDesc('posts_count')->values(),
            'tags' => $tags->allWithPostCounts($locale),
        ]);
    }
}
