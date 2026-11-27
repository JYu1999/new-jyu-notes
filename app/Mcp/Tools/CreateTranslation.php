<?php

namespace App\Mcp\Tools;

use App\Models\Post;
use App\Services\PostService;
use App\Services\TweetService;
use App\Support\AiPermissions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('create_translation')]
#[Description('Create (or update) the draft translation of a post or tweet in another language, with your translated title, excerpt and body. The original is never changed.')]
class CreateTranslation extends BlogTool
{
    protected string $permission = AiPermissions::DRAFT;

    public function handle(Request $request): Response
    {
        if ($denied = $this->denied()) {
            return $denied;
        }

        $data = $request->validate([
            'type' => 'required|in:post,tweet',
            'id' => 'required|integer',
            'locale' => 'required|in:zh,en,ja,vi,id',
            'title' => 'nullable|string|max:255',
            'body' => 'required|string|max:200000',
            'excerpt' => 'nullable|string|max:1000',
        ]);
        $source = $this->find($data['type'], $data['id']);
        if (! $source) {
            return Response::error('找不到原文。');
        }
        if ($source->locale === $data['locale']) {
            return Response::error('翻譯的語言不能和原文相同。');
        }

        if ($source instanceof Post) {
            $service = app(PostService::class);
            $translation = $service->createTranslation($source, $data['locale']);
            if ($translation->status !== 'draft') {
                return Response::error('這個語言版本已經發布，請用 update_content 修改。');
            }
            $translation = $service->update($translation, array_filter([
                'title' => $data['title'] ?? null, 'body' => $data['body'], 'excerpt' => $data['excerpt'] ?? null,
            ], fn ($v) => $v !== null));
        } else {
            $service = app(TweetService::class);
            $translation = $service->createTranslation($source, $data['locale']);
            if ($translation->status !== 'draft') {
                return Response::error('這個語言版本已經發布，請用 update_content 修改。');
            }
            $translation = $service->update($translation, ['body' => mb_substr($data['body'], 0, 2000)]);
        }

        return Response::json(['translation' => $this->summary($translation)]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(['post', 'tweet'])->required(),
            'id' => $schema->integer()->description('Id of the original')->required(),
            'locale' => $schema->string()->enum(['zh', 'en', 'ja', 'vi', 'id'])->required(),
            'title' => $schema->string(),
            'body' => $schema->string()->description('Translated Markdown body')->required(),
            'excerpt' => $schema->string(),
        ];
    }
}
