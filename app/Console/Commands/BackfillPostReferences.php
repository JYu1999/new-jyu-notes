<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\PostService;
use App\Support\PostReferenceExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillPostReferences extends Command
{
    protected $signature = 'posts:backfill-references {--dry-run : 只印出將會發生的變更，不寫入資料庫}';

    protected $description = 'Scan all posts and (re)populate post_references from internal links in their bodies.';

    public function handle(PostService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $extractor = new PostReferenceExtractor();

        $this->info($dryRun ? '== DRY RUN（不會寫入資料庫）==' : '== Backfill post_references ==');

        $scanned = 0;
        $withLinks = 0;
        $totalRefs = 0;
        $unresolvedTotal = 0;

        foreach (Post::all() as $post) {
            $scanned++;
            $pairs = $extractor->extract((string) $post->body);
            if (empty($pairs)) {
                continue;
            }

            $resolved = [];
            $unresolved = [];
            foreach ($pairs as $pair) {
                $target = Post::query()
                    ->where('locale', $pair['locale'])
                    ->where('slug', $pair['slug'])
                    ->first();

                if ($target && $target->id !== $post->id) {
                    $resolved[$target->id] = "{$pair['locale']}/{$pair['slug']}";
                } elseif (! $target) {
                    $unresolved[] = "{$pair['locale']}/{$pair['slug']}";
                }
            }

            $withLinks++;
            $totalRefs += count($resolved);
            $unresolvedTotal += count($unresolved);

            $line = sprintf(
                '[%s/%s] → %d 篇: %s',
                $post->locale,
                $post->slug,
                count($resolved),
                $resolved ? implode(', ', $resolved) : '(無可解析的目標)'
            );
            if ($unresolved) {
                $line .= '  ⚠ 找不到對應文章: '.implode(', ', $unresolved);
            }
            $this->line($line);

            if (! $dryRun) {
                $service->syncReferences($post);
            }
        }

        $this->newLine();
        $this->table(
            ['掃描文章', '含內部連結', '建立 reference', '無法解析(異常)'],
            [[$scanned, $withLinks, $totalRefs, $unresolvedTotal]]
        );

        if ($dryRun) {
            $this->warn('這是 dry-run，未寫入任何資料。確認上方結果無誤後，拿掉 --dry-run 再跑一次正式執行。');
        } else {
            $this->info('完成。post_references 目前共 '.DB::table('post_references')->count().' 筆。');
        }

        return self::SUCCESS;
    }
}
