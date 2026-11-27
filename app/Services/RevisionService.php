<?php

namespace App\Services;

use App\Models\ContentRevision;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tweet;
use App\Support\TextDiff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Version history for posts, tweets and pages (JYU-553).
 *
 * The content services call record() after every change. A revision is a full
 * snapshot of the editable fields; nothing is stored when nothing changed, and
 * quick successive edits by the same person (autosave) are folded into one.
 *
 * Bound as a scoped singleton so as() / asSystem() reach the services it wraps.
 */
class RevisionService
{
    /** Edits by the same actor within this many minutes become one revision. */
    public const COALESCE_MINUTES = 10;

    public const STATUS_LABELS = ['draft' => '草稿', 'published' => '已發布', 'hidden' => '隱藏'];

    /** Fields compared in the "欄位" line, per content type. */
    private const FIELDS = [
        'title' => '標題',
        'slug' => 'slug',
        'tags' => '標籤',
        'categories' => '分類',
        'status' => '狀態',
    ];

    /** @var array{kind: string, summary: string}|null */
    private ?array $context = null;

    /** @var array{0: string, 1: ?string, 2: ?int}|null */
    private ?array $actorOverride = null;

    /** Record the content's state from before revisions existed, so the first change has something to compare to. */
    public function ensureBaseline(Model $model): void
    {
        if (! $model->exists || $model->revisions()->exists()) {
            return;
        }

        $model->revisions()->create([
            'actor' => ContentRevision::ACTOR_SYSTEM,
            'actor_name' => null,
            'kind' => 'create',
            'summary' => '開始記錄版本前的內容',
            'snapshot' => $this->snapshot($model),
        ])->forceFill(['created_at' => $model->updated_at ?? now()])->save();
    }

    /** Snapshot the model after a change. Returns the new or updated revision, or null when nothing changed. */
    public function record(Model $model): ?ContentRevision
    {
        $model = $model::withTrashed()->find($model->getKey()) ?? $model;
        $snapshot = $this->snapshot($model);
        $last = $model->revisions()->latest('id')->first();
        $forced = $this->context;

        if ($last && ! $forced && $last->snapshot == $snapshot) {
            return null;
        }

        [$actor, $actorName, $userId] = $this->actor();
        $kind = $forced['kind'] ?? match (true) {
            $last === null => 'create',
            $this->onlyStatusChanged($last->snapshot, $snapshot) => 'status',
            default => 'edit',
        };

        $coalesce = $last && ! $forced && $kind === 'edit' && $last->kind === 'edit'
            && $last->actor === $actor && $last->actor_name === $actorName && $last->user_id === $userId
            && $last->created_at->gt(now()->subMinutes(self::COALESCE_MINUTES));

        $base = $coalesce
            ? $model->revisions()->where('id', '<', $last->id)->latest('id')->first()?->snapshot
            : $last?->snapshot;

        $counts = TextDiff::counts((string) ($base['body'] ?? ''), (string) ($snapshot['body'] ?? ''));
        $attributes = [
            'user_id' => $userId,
            'actor' => $actor,
            'actor_name' => $actorName,
            'kind' => $kind,
            'summary' => $forced['summary'] ?? $this->summarize($kind, $base, $snapshot),
            'snapshot' => $snapshot,
            'chars_added' => $base === null ? 0 : $counts['added'],
            'chars_removed' => $base === null ? 0 : $counts['removed'],
        ];

        if ($coalesce) {
            $last->forceFill($attributes + ['created_at' => now()])->save();

            return $last;
        }

        return $model->revisions()->create($attributes);
    }

    /** Run $fn with every revision it records labelled as $kind / $summary (e.g. a restore). */
    public function as(string $kind, string $summary, callable $fn): mixed
    {
        $this->context = ['kind' => $kind, 'summary' => $summary];
        try {
            return $fn();
        } finally {
            $this->context = null;
        }
    }

    /** Attribute every change in this request to an AI connection (JYU-554, set by the MCP middleware). */
    public function actAsAi(string $name, ?int $userId): void
    {
        $this->actorOverride = [ContentRevision::ACTOR_AI, $name, $userId];
    }

    /** Record changes made by the system (e.g. scheduled publishing) under a name. */
    public function asSystem(string $name, callable $fn): mixed
    {
        $this->actorOverride = [ContentRevision::ACTOR_SYSTEM, $name, null];
        try {
            return $fn();
        } finally {
            $this->actorOverride = null;
        }
    }

    /**
     * "還原成這一版之前": put the content back to the version before $revision.
     * Status is left alone so a restore never publishes or unpublishes anything.
     */
    public function restoreBefore(ContentRevision $revision): ?ContentRevision
    {
        $model = $revision->revisionable;
        $before = $model->revisions()->where('id', '<', $revision->id)->latest('id')->first();
        if (! $before) {
            return null;
        }

        $s = $before->snapshot;
        $summary = '還原成 '.$before->created_at->timezone(config('app.display_timezone'))->format('m/d H:i').' 的版本';

        return $this->as('restore', $summary, function () use ($model, $s) {
            match (true) {
                $model instanceof Post => app(PostService::class)->update($model, [
                    'title' => $s['title'],
                    'slug' => $s['slug'],
                    'excerpt' => $s['excerpt'] ?? null,
                    'body' => $s['body'],
                    'cover_image_path' => $s['cover_image_path'] ?? null,
                    'tag_ids' => $this->existingIds($s['tags'] ?? [], 'tags'),
                    'category_ids' => $this->existingIds($s['categories'] ?? [], 'categories'),
                    'categories_order' => collect($s['categories'] ?? [])->pluck('order', 'id')->all(),
                ]),
                $model instanceof Tweet => app(TweetService::class)->update($model, [
                    'body' => $s['body'],
                    'media' => $s['media'] ?? [],
                    'tag_ids' => $this->existingIds($s['tags'] ?? [], 'tags'),
                ]),
                $model instanceof Page => app(PageService::class)->update($model, [
                    'title' => $s['title'],
                    'slug' => $s['slug'],
                    'body' => $s['body'],
                    'cover_image_path' => $s['cover_image_path'] ?? null,
                ]),
            };

            // The service's own record() call ran inside this context, so it is the restore revision.
            return $model->revisions()->latest('id')->first();
        });
    }

    /** @return array<string, mixed> */
    public function snapshot(Model $model): array
    {
        $tags = fn () => $model->tags()->with('translations')->orderBy('tags.id')->get()
            ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name('zh') ?: '#'.$t->id])->values()->all();

        return match (true) {
            $model instanceof Post => [
                'title' => $model->title,
                'slug' => $model->slug,
                'excerpt' => $model->excerpt,
                'body' => (string) $model->body,
                'status' => $model->status,
                'cover_image_path' => $model->cover_image_path,
                'tags' => $tags(),
                'categories' => $model->categories()->with('translations')->orderBy('categories.id')->get()
                    ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name('zh') ?: '#'.$c->id, 'order' => $c->pivot->order_in_category])
                    ->values()->all(),
            ],
            $model instanceof Tweet => [
                'body' => (string) $model->body,
                'status' => $model->status,
                'media' => array_values($model->media ?? []),
                'tags' => $tags(),
            ],
            $model instanceof Page => [
                'title' => $model->title,
                'slug' => $model->slug,
                'body' => (string) $model->body,
                'status' => $model->status,
                'cover_image_path' => $model->cover_image_path,
            ],
        };
    }

    /**
     * Which of 標題 / slug / 標籤 / 分類 / 狀態 differ between two snapshots.
     *
     * @return array{changed: list<array{label: string, from: string, to: string}>, unchanged: list<string>}
     */
    public function fieldChanges(?array $from, array $to): array
    {
        $changed = [];
        $unchanged = [];
        foreach (self::FIELDS as $key => $label) {
            if (! array_key_exists($key, $to)) {
                continue;
            }
            $a = $this->display($key, $from[$key] ?? null);
            $b = $this->display($key, $to[$key]);
            if ($from !== null && $a !== $b) {
                $changed[] = ['label' => $label, 'from' => $a, 'to' => $b];
            } else {
                $unchanged[] = $label;
            }
        }

        return ['changed' => $changed, 'unchanged' => $unchanged];
    }

    /** Short label for the version list: "+86 −12", "狀態", "標籤"… */
    public function stat(ContentRevision $r, ?ContentRevision $previous): string
    {
        if ($r->chars_added || $r->chars_removed) {
            return trim(($r->chars_added ? '+'.$r->chars_added : '').' '.($r->chars_removed ? '−'.$r->chars_removed : ''));
        }
        if ($r->kind === 'status') {
            return '狀態';
        }
        if ($previous && ($previous->snapshot['tags'] ?? null) != ($r->snapshot['tags'] ?? null)) {
            return '標籤';
        }

        return '';
    }

    private function display(string $key, mixed $value): string
    {
        return match ($key) {
            'tags' => collect($value ?? [])->map(fn ($t) => '#'.$t['name'])->implode(' ') ?: '（無）',
            'categories' => collect($value ?? [])->pluck('name')->implode('、') ?: '（無）',
            'status' => self::STATUS_LABELS[$value] ?? (string) $value,
            default => (string) $value,
        };
    }

    private function summarize(string $kind, ?array $before, array $after): string
    {
        $status = fn ($s) => self::STATUS_LABELS[$s] ?? $s;
        if ($before === null || $kind === 'create') {
            return ($after['status'] ?? 'draft') === 'published' ? '建立並發布' : '建立草稿';
        }

        $parts = [];
        if (($before['title'] ?? null) !== ($after['title'] ?? null)) {
            $parts[] = '修改標題';
        }
        if (($before['body'] ?? '') !== ($after['body'] ?? '')) {
            $parts[] = '編輯內文';
        }
        if (($before['slug'] ?? null) !== ($after['slug'] ?? null)) {
            $parts[] = '修改網址';
        }
        if (($before['excerpt'] ?? null) !== ($after['excerpt'] ?? null)) {
            $parts[] = '修改摘要';
        }
        $oldTags = collect($before['tags'] ?? [])->pluck('name');
        $newTags = collect($after['tags'] ?? [])->pluck('name');
        foreach ($newTags->diff($oldTags) as $t) {
            $parts[] = '新增標籤 #'.$t;
        }
        foreach ($oldTags->diff($newTags) as $t) {
            $parts[] = '移除標籤 #'.$t;
        }
        if (($before['categories'] ?? null) != ($after['categories'] ?? null)) {
            $parts[] = '調整分類';
        }
        if (($before['cover_image_path'] ?? null) !== ($after['cover_image_path'] ?? null)) {
            $parts[] = '更換封面';
        }
        if (($before['media'] ?? null) != ($after['media'] ?? null)) {
            $parts[] = '更新照片';
        }

        $text = implode('、', array_slice($parts, 0, 3)).(count($parts) > 3 ? ' 等' : '');
        if (($before['status'] ?? null) !== ($after['status'] ?? null)) {
            $text = trim($text.' · '.$status($before['status'] ?? '').' → '.$status($after['status'] ?? ''), ' ·');
        }

        return $text !== '' ? $text : '更新';
    }

    private function onlyStatusChanged(array $before, array $after): bool
    {
        $b = $before;
        $a = $after;
        unset($b['status'], $a['status']);

        return ($before['status'] ?? null) !== ($after['status'] ?? null) && $b == $a;
    }

    /** @return array{0: string, 1: ?string, 2: ?int} */
    private function actor(): array
    {
        if ($this->actorOverride) {
            return $this->actorOverride;
        }

        // Changes from an AI connection are attributed by actAsAi() (EnsureAiConnection).
        $user = auth()->user();

        return $user
            ? [ContentRevision::ACTOR_USER, $user->name, $user->id]
            : [ContentRevision::ACTOR_SYSTEM, null, null];
    }

    /** @return list<int> ids from a snapshot list that still exist */
    private function existingIds(array $items, string $table): array
    {
        $ids = collect($items)->pluck('id')->filter()->all();

        return $ids ? DB::table($table)->whereIn('id', $ids)->pluck('id')->all() : [];
    }

    /** Group revisions by day (display timezone) for the list. */
    public static function dayLabel(Carbon $at): string
    {
        $local = $at->copy()->timezone(config('app.display_timezone'));
        $today = now(config('app.display_timezone'));

        return match (true) {
            $local->isSameDay($today) => '今天 '.$local->format('m/d'),
            $local->isSameDay($today->copy()->subDay()) => '昨天 '.$local->format('m/d'),
            default => $local->format('Y/m/d'),
        };
    }
}
