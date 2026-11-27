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
#[Description('Search posts, tweets and pages (including drafts) by keyword in the title, slug or body. Returns up to 20 matches with their type, id, locale and status.')]
#[IsReadOnly]
class SearchContent extends BlogTool
{
    public function handle(Request $request): Response
    {
        if ($denied = $this->denied()) {
            return $denied;
        }

        $data = $request->validate([
            'query' => 'required|string|max:200',
            'type' => 'nullable|in:all,post,tweet,page',
            'locale' => 'nullable|in:zh,en,ja,vi,id',
            'limit' => 'nullable|integer|min:1|max:20',
        ]);
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $data['query']).'%';
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
                ->where(fn ($q) => $key === 'tweet'
                    ? $q->where('body', 'ILIKE', $like)
                    : $q->where('title', 'ILIKE', $like)->orWhere('slug', 'ILIKE', $like)->orWhere('body', 'ILIKE', $like))
                ->latest('updated_at')->limit($limit)->get());
        }

        return Response::json($results->sortByDesc('updated_at')->take($limit)->map(fn ($m) => $this->summary($m))->values()->all());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Keyword to search for')->required(),
            'type' => $schema->string()->enum(['all', 'post', 'tweet', 'page'])->description('Limit to one content type (default all)'),
            'locale' => $schema->string()->enum(['zh', 'en', 'ja', 'vi', 'id'])->description('Limit to one language'),
            'limit' => $schema->integer()->description('Max results, 1-20 (default 10)'),
        ];
    }
}
