<?php

namespace App\Mcp\Tools;

use App\Models\Media;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_media')]
#[Description('List the most recent files in the media library (images and videos) with their URL, so they can be reused in posts.')]
#[IsReadOnly]
class ListMedia extends BlogTool
{
    protected string $action = 'read';

    protected function run(Request $request): Response
    {
        $data = $request->validate(['limit' => 'nullable|integer|min:1|max:100']);

        return Response::json(Media::query()->latest('id')->limit($data['limit'] ?? 30)->get()
            ->map(fn (Media $m) => [
                'id' => $m->id,
                'url' => media_url($m->path),
                'mime_type' => $m->mime_type,
                'filename' => $m->original_filename,
                'width' => $m->width,
                'height' => $m->height,
            ])->all());
    }

    protected function describe(array $args): string
    {
        return '列出媒體庫（唯讀）';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->description('How many, 1-100 (default 30)'),
        ];
    }
}
