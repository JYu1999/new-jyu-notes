<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\TodoService;
use Illuminate\View\View;

class ChangelogController extends Controller
{
    public function index(TodoService $service): View
    {
        return view('public.changelog', [
            'groups' => $service->changelogGrouped(),
        ]);
    }
}
