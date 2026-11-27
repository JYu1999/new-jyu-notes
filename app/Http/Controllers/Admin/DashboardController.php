<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\TagRepository;
use App\Services\AiApprovalService;
use App\Services\DraftService;
use App\Services\MediaUsageService;
use App\Services\TranslationStatusService;
use App\Services\ViewStatsService;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(
        DraftService $drafts,
        TagRepository $tags,
        ViewStatsService $views,
        TranslationStatusService $translations,
        MediaUsageService $media,
    ): View {
        return view('admin.dashboard', [
            'greeting' => self::greeting(Carbon::now(config('app.display_timezone'))),
            'drafts' => $drafts->recent(3),
            'draftCount' => $drafts->count(),
            'tags' => $tags->all(),
            'views' => $views->summary(),
            // AI requests waiting for approval come first (JYU-556).
            'attention' => array_values(array_filter([...app(AiApprovalService::class)->attentionItems(), ...$translations->attentionItems(), $media->attentionItem()])),
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
