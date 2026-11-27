<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_content')]
#[Description('Read one post, tweet or page in full: body (Markdown), status, tags, categories and its other language versions.')]
#[IsReadOnly]
class GetContent extends BlogTool
{
    public function handle(Request $request): Response
    {
        if ($denied = $this->denied()) {
            return $denied;
        }

        $data = $request->validate(['type' => 'required|in:post,tweet,page', 'id' => 'required|integer']);
        $model = $this->find($data['type'], $data['id']);

        return $model ? Response::json($this->detail($model)) : Response::error('找不到這筆內容。');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(['post', 'tweet', 'page'])->required(),
            'id' => $schema->integer()->required(),
        ];
    }
}
