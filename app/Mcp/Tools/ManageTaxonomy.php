<?php

namespace App\Mcp\Tools;

use App\Models\Post;
use App\Services\CategoryService;
use App\Services\PostService;
use App\Services\TagService;
use App\Services\TweetService;
use App\Support\AiPermissions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('manage_taxonomy')]
#[Description('Organise content: create a tag, create a category, or set the tags of a post / tweet (by tag name; replaces its tags). Nothing can be deleted.')]
class ManageTaxonomy extends BlogTool
{
    protected string $permission = AiPermissions::ORGANIZE;

    public function handle(Request $request): Response
    {
        if ($denied = $this->denied()) {
            return $denied;
        }

        $data = $request->validate([
            'action' => 'required|in:create_tag,create_category,set_tags',
            'name' => 'required_if:action,create_tag,create_category|nullable|string|max:100',
            'locale' => 'nullable|in:zh,en,ja,vi,id',
            'description' => 'nullable|string|max:1000',
            'type' => 'required_if:action,set_tags|nullable|in:post,tweet',
            'id' => 'required_if:action,set_tags|nullable|integer',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:100',
        ]);
        $locale = $data['locale'] ?? 'zh';

        if ($data['action'] === 'create_tag') {
            $tag = app(TagService::class)->create(['translations' => [['locale' => $locale, 'name' => $data['name']]]]);

            return Response::json(['tag' => ['id' => $tag->id, 'name' => $data['name']]]);
        }

        if ($data['action'] === 'create_category') {
            $category = app(CategoryService::class)->create(['translations' => [[
                'locale' => $locale, 'name' => $data['name'], 'description' => $data['description'] ?? null,
            ]]]);

            return Response::json(['category' => ['id' => $category->id, 'name' => $data['name']]]);
        }

        $model = $this->find($data['type'], $data['id']);
        if (! $model) {
            return Response::error('找不到這筆內容。');
        }
        $ids = $this->tagIds($data['tags'] ?? []);
        $model = $model instanceof Post
            ? app(PostService::class)->update($model, ['tag_ids' => $ids])
            : app(TweetService::class)->update($model, ['tag_ids' => $ids]);

        return Response::json(['updated' => $this->detail($model)['tags'] ?? []]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->enum(['create_tag', 'create_category', 'set_tags'])->required(),
            'name' => $schema->string()->description('Tag / category name (create_*)'),
            'locale' => $schema->string()->enum(['zh', 'en', 'ja', 'vi', 'id'])->description('Language of the name (default zh)'),
            'description' => $schema->string()->description('Category description'),
            'type' => $schema->string()->enum(['post', 'tweet'])->description('set_tags: content type'),
            'id' => $schema->integer()->description('set_tags: content id'),
            'tags' => $schema->array()->items($schema->string())->description('set_tags: tag names (unknown names are ignored)'),
        ];
    }
}
