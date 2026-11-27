<?php

namespace App\Mcp\Tools;

use App\Models\AiPublishRequest;
use App\Support\AiPermissions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('request_publish')]
#[Description('Ask the author to publish or hide a post, tweet or page. Nothing changes until the author approves it in the admin.')]
class RequestPublish extends BlogTool
{
    protected string $permission = AiPermissions::PUBLISH;

    public function handle(Request $request): Response
    {
        if ($denied = $this->denied()) {
            return $denied;
        }

        $data = $request->validate([
            'type' => 'required|in:post,tweet,page',
            'id' => 'required|integer',
            'action' => 'required|in:publish,hide',
            'note' => 'nullable|string|max:1000',
        ]);
        $model = $this->find($data['type'], $data['id']);
        if (! $model) {
            return Response::error('找不到這筆內容。');
        }

        $pending = AiPublishRequest::query()->firstOrCreate([
            'publishable_type' => $model->getMorphClass(),
            'publishable_id' => $model->getKey(),
            'action' => $data['action'],
            'status' => AiPublishRequest::STATUS_PENDING,
        ], [
            'ai_connection_id' => $this->connection()?->id,
            'note' => $data['note'] ?? null,
        ]);

        return Response::json([
            'request_id' => $pending->id,
            'status' => 'pending',
            'message' => '已送出，等作者在後台核准後才會生效。',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(['post', 'tweet', 'page'])->required(),
            'id' => $schema->integer()->required(),
            'action' => $schema->string()->enum(['publish', 'hide'])->required(),
            'note' => $schema->string()->description('Why, for the author'),
        ];
    }
}
