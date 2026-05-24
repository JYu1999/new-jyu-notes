<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\SearchRequest;
use App\Services\SearchService;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function index(SearchRequest $request, SearchService $search): View
    {
        $validated = $request->validated();
        $query = $validated['q'] ?? '';
        $type = $validated['type'] ?? 'all';
        $locale = app()->getLocale();

        $results = $search->fullText($query, $locale, $type);

        $data = [
            'q' => $query,
            'type' => $type,
            'results' => $results,
        ];

        // AJAX live search: return only the results partial
        if ($request->boolean('partial')) {
            return view('public._search-results', $data);
        }

        return view('public.search', $data);
    }
}
