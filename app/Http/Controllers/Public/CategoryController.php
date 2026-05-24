<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Repositories\CategoryRepository;
use App\Repositories\PostRepository;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function show(
        string $locale,
        string $slug,
        CategoryRepository $categories,
        PostRepository $posts,
    ): View {
        $category = $categories->findBySlug($locale, $slug);
        abort_if(! $category, 404);

        return view('public.categories.show', [
            'category' => $category,
            'posts' => $posts->byCategory($category, $locale, 12),
        ]);
    }
}
