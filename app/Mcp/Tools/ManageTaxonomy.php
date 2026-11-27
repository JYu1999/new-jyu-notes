<?php

namespace App\Mcp\Tools;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Services\CategoryService;
use App\Services\PostService;
use App\Services\TagService;
use App\Services\TweetService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('manage_taxonomy')]
#[Description('Organise content: create or rename a tag, create or update a category (name, description), or set the tags of a post / tweet (by tag name; replaces its tags). Names are per language. Nothing can be deleted.')]
class ManageTaxonomy extends BlogTool
{
    protected string $action = 'taxonomy';

    protected function run(Request $request): Response
    {
        $data = $request->validate([
            'action' => 'required|in:create_tag,create_category,update_tag,update_category,set_tags',
            'name' => 'required_if:action,create_tag,create_category,update_tag|nullable|string|max:100',
            'tag_id' => 'required_if:action,update_tag|nullable|integer|exists:tags,id',
            'category_id' => 'required_if:action,update_category|nullable|integer|exists:categories,id',
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

        if ($data['action'] === 'update_tag') {
            $tag = Tag::query()->with('translations')->findOrFail($data['tag_id']);
            $translations = $tag->translations->mapWithKeys(fn ($t) => [$t->locale => ['locale' => $t->locale, 'name' => $t->name]])->all();
            $translations[$locale] = ['locale' => $locale, 'name' => $data['name']];
            app(TagService::class)->update($tag, ['translations' => array_values($translations)]);

            return Response::json(['tag' => ['id' => $tag->id, 'names' => collect($translations)->pluck('name', 'locale')]]);
        }

        if ($data['action'] === 'update_category') {
            $category = Category::query()->with('translations')->findOrFail($data['category_id']);
            $translations = $category->translations->mapWithKeys(fn ($t) => [$t->locale => [
                'locale' => $t->locale, 'name' => $t->name, 'description' => $t->description,
            ]])->all();
            $current = $translations[$locale] ?? ['locale' => $locale, 'name' => $data['name'] ?? '', 'description' => null];
            $translations[$locale] = [
                'locale' => $locale,
                'name' => $data['name'] ?? $current['name'],
                'description' => array_key_exists('description', $data) && $data['description'] !== null ? $data['description'] : $current['description'],
            ];
            if ($translations[$locale]['name'] === '') {
                return Response::error('這個語言還沒有分類名稱，請提供 name。');
            }
            app(CategoryService::class)->update($category, ['translations' => array_values($translations)]);

            return Response::json(['category' => ['id' => $category->id, 'names' => collect($translations)->pluck('name', 'locale')]]);
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

    protected function describe(array $args): string
    {
        return match ($args['action'] ?? null) {
            'create_tag' => '新增標籤 #'.($args['name'] ?? ''),
            'create_category' => '新增分類「'.($args['name'] ?? '').'」',
            'update_tag' => '把標籤改名為 #'.($args['name'] ?? ''),
            'update_category' => '更新分類'.(isset($args['name']) ? '「'.$args['name'].'」' : ''),
            default => '設定'.$this->label($args).'的標籤：'.implode('、', array_map(fn ($t) => '#'.$t, $args['tags'] ?? [])),
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->enum(['create_tag', 'create_category', 'update_tag', 'update_category', 'set_tags'])->required(),
            'tag_id' => $schema->integer()->description('update_tag: tag id (see list_taxonomy)'),
            'category_id' => $schema->integer()->description('update_category: category id (see list_taxonomy)'),
            'name' => $schema->string()->description('Tag / category name in `locale` (create_*, update_*)'),
            'locale' => $schema->string()->enum(['zh', 'en', 'ja', 'vi', 'id'])->description('Language of the name (default zh)'),
            'description' => $schema->string()->description('Category description'),
            'type' => $schema->string()->enum(['post', 'tweet'])->description('set_tags: content type'),
            'id' => $schema->integer()->description('set_tags: content id'),
            'tags' => $schema->array()->items($schema->string())->description('set_tags: tag names (unknown names are ignored)'),
        ];
    }
}
