<?php

namespace App\Mcp\Tools;

use App\Models\Page;
use App\Models\Post;
use App\Services\PageService;
use App\Services\PostService;
use App\Services\TweetService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('update_content')]
#[Description('Edit the title, body, excerpt or slug of a post, tweet or page. Status cannot be changed here (use request_publish). Every change is kept in the version history and can be restored.')]
class UpdateContent extends BlogTool
{
    protected string $action = 'draft';

    protected function run(Request $request): Response
    {
        $data = $request->validate([
            'type' => 'required|in:post,tweet,page',
            'id' => 'required|integer',
            'title' => 'nullable|string|max:255',
            'body' => 'nullable|string|max:200000',
            'excerpt' => 'nullable|string|max:1000',
            'slug' => ['nullable', 'string', 'max:255', 'not_regex:~[/\\\\?#&\s]~'],
        ]);
        $model = $this->find($data['type'], $data['id']);
        if (! $model) {
            return Response::error('找不到這筆內容。');
        }

        $fields = array_filter(array_intersect_key($data, array_flip(['title', 'body', 'excerpt', 'slug'])), fn ($v) => $v !== null);
        if (! $fields) {
            return Response::error('沒有要修改的欄位。');
        }

        $model = match (true) {
            $model instanceof Post => app(PostService::class)->update($model, $fields),
            $model instanceof Page => app(PageService::class)->update($model, $fields),
            default => app(TweetService::class)->update($model, array_intersect_key($fields, ['body' => true])),
        };

        return Response::json(['updated' => $this->summary($model)]);
    }

    protected function describe(array $args): string
    {
        return '更新'.$this->label($args);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(['post', 'tweet', 'page'])->required(),
            'id' => $schema->integer()->required(),
            'title' => $schema->string(),
            'body' => $schema->string()->description('Markdown, replaces the whole body'),
            'excerpt' => $schema->string(),
            'slug' => $schema->string(),
        ];
    }
}
