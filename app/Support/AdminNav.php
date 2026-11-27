<?php

namespace App\Support;

use App\Models\Page;
use App\Models\Post;
use App\Models\Tweet;
use Illuminate\Http\Request;

/**
 * Admin sidebar (JYU-541): grouped items and content counts.
 * A post / tweet / page written in several languages counts once (per translation group).
 */
class AdminNav
{
    /**
     * @return list<array{label: string, items: list<array{label: string, url: string, active: bool, count: ?int}>}>
     */
    public static function groups(Request $request): array
    {
        $counts = self::counts();
        $item = fn (string $label, string $route, string $match, ?int $count = null) => [
            'label' => $label,
            'url' => route($route),
            'active' => $request->routeIs($match),
            'count' => $count,
        ];

        return [
            ['label' => '寫作', 'items' => [
                $item('首頁', 'admin.dashboard', 'admin.dashboard'),
                $item('文章', 'admin.posts.index', 'admin.posts.*', $counts['posts']),
                $item('碎念', 'admin.tweets.index', 'admin.tweets.*', $counts['tweets']),
                $item('頁面', 'admin.pages.index', 'admin.pages.*', $counts['pages']),
            ]],
            ['label' => '整理', 'items' => [
                $item('分類與系列', 'admin.categories.index', 'admin.categories.*'),
                $item('標籤', 'admin.tags.index', 'admin.tags.*'),
                $item('媒體庫', 'admin.media.index', 'admin.media.*'),
            ]],
            ['label' => '網站', 'items' => [
                $item('AI 連線', 'admin.agents.index', 'admin.agents.*'),
                $item('API Tokens', 'admin.tokens.index', 'admin.tokens.*'),
                $item('Todos', 'admin.todos.index', 'admin.todos.*'),
            ]],
        ];
    }

    /** @return array{posts: int, tweets: int, pages: int} */
    public static function counts(): array
    {
        return [
            'posts' => Post::query()->distinct()->count('post_group_id'),
            'tweets' => Tweet::query()->distinct()->count('tweet_group_id'),
            'pages' => Page::query()->distinct()->count('page_group_id'),
        ];
    }
}
