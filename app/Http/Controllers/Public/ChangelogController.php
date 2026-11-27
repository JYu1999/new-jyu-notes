<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\ChangelogService;
use Illuminate\View\View;

class ChangelogController extends Controller
{
    public function index(ChangelogService $changelog): View
    {
        return view('public.changelog', [
            'groups' => $changelog->grouped(),
        ]);
    }
}
