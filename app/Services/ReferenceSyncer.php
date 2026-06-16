<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Tweet;
use App\Support\ReferenceExtractor;
use Illuminate\Database\Eloquent\Model;

class ReferenceSyncer
{
    /**
     * 解析 $source->body 內部連結,覆寫此 source 的 outgoing references。
     */
    public function sync(Model $source): void
    {
        $entries = (new ReferenceExtractor())->extract((string) ($source->body ?? ''));

        $targets = []; // dedupe key "type:id" => ['type' => , 'id' => ]
        foreach ($entries as $entry) {
            $target = $this->resolve($entry);
            if (! $target) {
                continue;
            }
            // skip self-reference (same type + same id)
            if ($target->getMorphClass() === $source->getMorphClass() && $target->getKey() === $source->getKey()) {
                continue;
            }
            $targets[$target->getMorphClass().':'.$target->getKey()] = [
                'type' => $target->getMorphClass(),
                'id' => $target->getKey(),
            ];
        }

        // Overwrite: drop all existing outgoing, re-insert the resolved set.
        $source->outgoingReferences()->delete();
        foreach ($targets as $t) {
            $source->outgoingReferences()->create([
                'target_type' => $t['type'],
                'target_id' => $t['id'],
            ]);
        }
    }

    private function resolve(array $entry): ?Model
    {
        if ($entry['type'] === 'post') {
            return Post::query()
                ->where('locale', $entry['locale'])
                ->where('slug', $entry['slug'])
                ->first();
        }

        if ($entry['type'] === 'tweet') {
            return Tweet::query()->find($entry['id']);
        }

        return null;
    }
}
