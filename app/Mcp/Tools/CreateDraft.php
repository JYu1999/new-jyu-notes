<?php

namespace App\Mcp\Tools;

use App\Services\PostService;
use App\Services\TweetService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('create_draft')]
#[Description('Create a new post or tweet as a draft (never published). Body is Markdown. Existing tags can be attached by name.')]
class CreateDraft extends BlogTool
{
    protected string $action = 'draft';

    protected function run(Request $request): Response
    {
        $data = $request->validate([
            'type' => 'required|in:post,tweet',
            'locale' => 'required|in:zh,en,ja,vi,id',
            'title' => 'required_if:type,post|nullable|string|max:255',
            'body' => 'required|string|max:200000',
            'excerpt' => 'nullable|string|max:1000',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:100',
        ]);
        $tagIds = $this->tagIds($data['tags'] ?? []);

        $model = $data['type'] === 'post'
            ? app(PostService::class)->create([
                'locale' => $data['locale'], 'title' => $data['title'], 'body' => $data['body'],
                'excerpt' => $data['excerpt'] ?? null, 'status' => 'draft', 'tag_ids' => $tagIds,
            ])
            : app(TweetService::class)->create([
                'locale' => $data['locale'], 'body' => mb_substr($data['body'], 0, 2000), 'status' => 'draft', 'tag_ids' => $tagIds,
            ]);

        $this->subject = $model;

        return Response::json(['created' => $this->summary($model)]);
    }

    protected function describe(array $args): string
    {
        return '建立'.(($args['type'] ?? '') === 'tweet' ? '碎念' : '文章').'草稿〈'.mb_strimwidth((string) ($args['title'] ?? $args['body'] ?? ''), 0, 40, '…').'〉';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(['post', 'tweet'])->required(),
            'locale' => $schema->string()->enum(['zh', 'en', 'ja', 'vi', 'id'])->required(),
            'title' => $schema->string()->description('Required for posts'),
            'body' => $schema->string()->description('Markdown')->required(),
            'excerpt' => $schema->string(),
            'tags' => $schema->array()->items($schema->string())->description('Names of existing tags'),
        ];
    }
}
