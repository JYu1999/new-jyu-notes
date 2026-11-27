<?php

namespace App\Mcp\Tools;

use App\Models\Category;
use App\Models\Tag;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_taxonomy')]
#[Description('List all tags and categories (series) with their id, names in each language and how many posts use them.')]
#[IsReadOnly]
class ListTaxonomy extends BlogTool
{
    protected string $action = 'read';

    protected function run(Request $request): Response
    {
        $names = fn ($m) => $m->translations->pluck('name', 'locale')->all();

        return Response::json([
            'tags' => Tag::query()->with('translations')->withCount('posts')->orderBy('id')->get()
                ->map(fn ($t) => ['id' => $t->id, 'names' => $names($t), 'posts' => $t->posts_count])->all(),
            'categories' => Category::query()->with('translations')->withCount('posts')->orderBy('id')->get()
                ->map(fn ($c) => ['id' => $c->id, 'names' => $names($c), 'posts' => $c->posts_count])->all(),
        ]);
    }

    protected function describe(array $args): string
    {
        return '列出分類與標籤（唯讀）';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
