<?php

namespace App\Mcp\Tools;

use App\Models\AiConnection;
use App\Models\Page;
use App\Models\Post;
use App\Models\TagTranslation;
use App\Models\Tweet;
use App\Support\AiPermissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Shared helpers for the blog's MCP tools (JYU-554). Each tool declares the
 * permission it needs; tools the connection isn't allowed to use are not even
 * listed, and are refused if called anyway.
 */
abstract class BlogTool extends Tool
{
    public const TYPES = ['post' => Post::class, 'tweet' => Tweet::class, 'page' => Page::class];

    protected string $permission = AiPermissions::READ;

    public function shouldRegister(): bool
    {
        return $this->connection()?->allows($this->permission) ?? false;
    }

    protected function connection(): ?AiConnection
    {
        return app()->bound(AiConnection::class) ? app(AiConnection::class) : null;
    }

    /** An error response when the connection lacks this tool's permission. */
    protected function denied(): ?Response
    {
        return $this->shouldRegister()
            ? null
            : Response::error('這個 AI 連線沒有「'.AiPermissions::ALL[$this->permission]['label'].'」的權限。');
    }

    protected function find(string $type, int $id): ?Model
    {
        $class = self::TYPES[$type] ?? null;

        return $class ? $class::query()->find($id) : null;
    }

    /** @return array<string, mixed> */
    protected function summary(Model $m): array
    {
        $type = array_search($m::class, self::TYPES, true);

        return array_filter([
            'type' => $type,
            'id' => $m->id,
            'locale' => $m->locale,
            'status' => $m->status,
            'title' => $m instanceof Tweet ? Str::limit(strip_tags((string) $m->body), 60) : $m->title,
            'slug' => $m->slug ?? null,
            'updated_at' => $m->updated_at?->toIso8601String(),
            'url' => $this->publicUrl($m),
        ], fn ($v) => $v !== null);
    }

    /** @return array<string, mixed> */
    protected function detail(Model $m): array
    {
        $groupKey = match (true) {
            $m instanceof Post => 'post_group_id',
            $m instanceof Tweet => 'tweet_group_id',
            default => 'page_group_id',
        };

        return $this->summary($m) + array_filter([
            'body' => (string) $m->body,
            'excerpt' => $m instanceof Post ? $m->excerpt : null,
            'tags' => method_exists($m, 'tags') ? $m->tags()->with('translations')->get()->map(fn ($t) => $t->name('zh'))->values()->all() : null,
            'categories' => $m instanceof Post ? $m->categories()->with('translations')->get()->map(fn ($c) => $c->name('zh'))->values()->all() : null,
            'media' => $m instanceof Tweet ? $m->media : null,
            'translations' => $m::query()->where($groupKey, $m->{$groupKey})->where('id', '!=', $m->id)
                ->get(['id', 'locale', 'status'])->map(fn ($t) => ['id' => $t->id, 'locale' => $t->locale, 'status' => $t->status])->values()->all(),
        ], fn ($v) => $v !== null);
    }

    protected function publicUrl(Model $m): ?string
    {
        if ($m->status !== 'published') {
            return null;
        }

        return match (true) {
            $m instanceof Post => route('public.posts.show', [$m->locale, $m->slug]),
            $m instanceof Tweet => route('public.tweets.show', [$m->locale, $m->id]),
            default => url('/'.$m->locale.'/'.$m->slug),
        };
    }

    /** Existing tag ids for the given names (any language). */
    protected function tagIds(array $names): array
    {
        return TagTranslation::query()->whereIn('name', array_map('trim', $names))->distinct()->pluck('tag_id')->all();
    }
}
