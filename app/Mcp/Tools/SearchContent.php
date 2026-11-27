<?php

namespace App\Mcp\Tools;

use App\Models\Page;
use App\Models\Post;
use App\Models\Tweet;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('search_content')]
#[Description('Find posts, tweets and pages (including drafts): by keyword in the title, slug or body, or without a keyword to list the most recently updated. Filter by type, language and status. Returns up to 50 with their type, id, locale and status.')]
#[IsReadOnly]
class SearchContent extends BlogTool
{
    protected string $action = 'read';

    protected function run(Request $request): Response
    {
        $data = $request->validate([
            'query' => 'nullable|string|max:200',
            'status' => 'nullable|in:draft,published,hidden',
            'type' => 'nullable|in:all,post,tweet,page',
            'locale' => 'nullable|in:zh,en,ja,vi,id',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);
        $like = filled($data['query'] ?? null) ? '%'.str_replace(['%', '_'], ['\\%', '\\_'], $data['query']).'%' : null;
        $type = $data['type'] ?? 'all';
        $limit = $data['limit'] ?? 10;
        $locale = $data['locale'] ?? null;

        $results = collect();
        foreach (['post' => Post::class, 'tweet' => Tweet::class, 'page' => Page::class] as $key => $class) {
            if ($type !== 'all' && $type !== $key) {
                continue;
            }
            $results = $results->merge($class::query()
                ->when($locale, fn ($q) => $q->where('locale', $locale))
                ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
                ->when($like, fn ($q) => $q->where(fn ($q) => $key === 'tweet'
                    ? $q->where('body', 'ILIKE', $like)
                    : $q->where('title', 'ILIKE', $like)->orWhere('slug', 'ILIKE', $like)->orWhere('body', 'ILIKE', $like)))
                ->latest('updated_at')->limit($limit)->get());
        }

        return Response::json($results->sortByDesc('updated_at')->take($limit)->map(fn ($m) => $this->summary($m))->values()->all());
    }

    protected function describe(array $args): string
    {
        return filled($args['query'] ?? null) ? '搜尋「'.$args['query'].'」相關內容（唯讀）' : '列出最近的內容（唯讀）';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Keyword to search for (omit to list the latest)'),
            'status' => $schema->string()->enum(['draft', 'published', 'hidden'])->description('Only this status'),
            'type' => $schema->string()->enum(['all', 'post', 'tweet', 'page'])->description('Limit to one content type (default all)'),
            'locale' => $schema->string()->enum(['zh', 'en', 'ja', 'vi', 'id'])->description('Limit to one language'),
            'limit' => $schema->integer()->description('Max results, 1-50 (default 10)'),
        ];
    }
}
