<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\TagRepository;
use App\Services\DraftService;
use App\Services\ViewStatsService;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(DraftService $drafts, TagRepository $tags, ViewStatsService $views): View
    {
        return view('admin.dashboard', [
            'greeting' => self::greeting(Carbon::now(config('app.display_timezone'))),
            'drafts' => $drafts->recent(3),
            'draftCount' => $drafts->count(),
            'tags' => $tags->all(),
            'views' => $views->summary(),
        ]);
    }

    public function drafts(DraftService $drafts): View
    {
        return view('admin.drafts', ['drafts' => $drafts->recent()]);
    }

    public static function greeting(Carbon $now): string
    {
        return match (true) {
            $now->hour >= 5 && $now->hour < 11 => '早安',
            $now->hour >= 11 && $now->hour < 18 => '午安',
            default => '晚上好',
        };
    }
}
