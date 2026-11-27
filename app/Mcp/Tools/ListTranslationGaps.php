<?php

namespace App\Mcp\Tools;

use App\Services\TranslationStatusService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_translation_gaps')]
#[Description('List posts that have no translation in a language yet ("missing"), and translations older than their original ("stale").')]
#[IsReadOnly]
class ListTranslationGaps extends BlogTool
{
    public function handle(Request $request, TranslationStatusService $status): Response
    {
        if ($denied = $this->denied()) {
            return $denied;
        }

        $data = $request->validate(['locale' => 'nullable|in:en,ja,vi,id']);
        $locales = isset($data['locale']) ? [$data['locale']] : $status->activeTargetLocales();

        $out = [];
        foreach ($locales as $locale) {
            $out[$locale] = [
                'missing' => $status->missing($locale)->limit(50)->get()->map(fn ($p) => $this->summary($p))->values()->all(),
                'stale' => $status->stale($locale)->limit(50)->get()->map(fn ($p) => $this->summary($p))->values()->all(),
            ];
        }

        return Response::json($out);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'locale' => $schema->string()->enum(['en', 'ja', 'vi', 'id'])->description('Only this language (default: every language in use)'),
        ];
    }
}
