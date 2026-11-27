<?php

namespace App\Mcp\Tools;

use App\Models\Post;
use App\Models\Tweet;
use App\Services\PageService;
use App\Services\PostService;
use App\Services\TweetService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('request_publish')]
#[Description('Publish or hide a post, tweet or page. Depending on the author\'s settings this is either done right away or sent to the author for approval first; the response says which.')]
class RequestPublish extends BlogTool
{
    protected string $action = 'publish';

    protected function run(Request $request): Response
    {
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

        // Runs right away when the policy allows it; by default the policy is "需確認",
        // so BlogTool queues the call and it runs here once the author approves (JYU-555 / JYU-556).
        $status = $data['action'] === 'hide' ? 'hidden' : 'published';
        match (true) {
            $model instanceof Post => app(PostService::class)->updateStatus($model, $status),
            $model instanceof Tweet => app(TweetService::class)->updateStatus($model, $status),
            default => app(PageService::class)->update($model, ['status' => $status]),
        };
        $this->subject = $model->fresh();

        return Response::json(['done' => $this->summary($this->subject)]);
    }

    protected function describe(array $args): string
    {
        return (($args['action'] ?? '') === 'hide' ? '隱藏' : '發布').$this->label($args);
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
