<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\Tweet;
use App\Services\ReferenceSyncer;
use App\Support\ReferenceExtractor;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BackfillReferences extends Command
{
    protected $signature = 'references:backfill {--dry-run : 只印出將會發生的變更,不寫入資料庫}';

    protected $description = 'Scan all posts and tweets and (re)populate content_references from internal links.';

    public function handle(ReferenceSyncer $syncer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $extractor = new ReferenceExtractor();

        $this->info($dryRun ? '== DRY RUN（不會寫入資料庫）==' : '== Backfill content_references ==');

        $scanned = 0;
        $withLinks = 0;
        $totalRefs = 0;
        $unresolvedTotal = 0;

        foreach (Post::all()->concat(Tweet::all()) as $source) {
            $scanned++;
            $entries = $extractor->extract((string) $source->body);
            if (empty($entries)) {
                continue;
            }

            $resolved = [];
            $unresolved = [];
            foreach ($entries as $entry) {
                $target = $this->resolve($entry);
                if ($target && ! ($target->getMorphClass() === $source->getMorphClass() && $target->getKey() === $source->getKey())) {
                    $resolved[] = $this->label($entry);
                } elseif (! $target) {
                    $unresolved[] = $this->label($entry);
                }
            }

            $withLinks++;
            $totalRefs += count($resolved);
            $unresolvedTotal += count($unresolved);

            $line = sprintf(
                '[%s #%s] → %d 筆: %s',
                $source->getMorphClass(),
                $source->getKey(),
                count($resolved),
                $resolved ? implode(', ', $resolved) : '(無可解析的目標)'
            );
            if ($unresolved) {
                $line .= '  ⚠ 找不到對應: '.implode(', ', $unresolved);
            }
            $this->line($line);

            if (! $dryRun) {
                $syncer->sync($source);
            }
        }

        $this->newLine();
        $this->table(
            ['掃描', '含內部連結', '建立 reference', '無法解析(異常)'],
            [[$scanned, $withLinks, $totalRefs, $unresolvedTotal]]
        );

        if ($dryRun) {
            $this->warn('這是 dry-run,未寫入任何資料。確認無誤後拿掉 --dry-run 再跑。');
        } else {
            $this->info('完成。content_references 目前共 '.DB::table('content_references')->count().' 筆。');
        }

        return self::SUCCESS;
    }

    private function resolve(array $entry): ?Model
    {
        if ($entry['type'] === 'post') {
            return Post::query()->where('locale', $entry['locale'])->where('slug', $entry['slug'])->first();
        }
        if ($entry['type'] === 'tweet') {
            return Tweet::query()->find($entry['id']);
        }

        return null;
    }

    private function label(array $entry): string
    {
        return $entry['type'] === 'post'
            ? "posts/{$entry['slug']}"
            : "tweets/{$entry['id']}";
    }
}
