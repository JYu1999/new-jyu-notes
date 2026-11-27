<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RevisionIndexRequest;
use App\Models\ContentRevision;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tweet;
use App\Services\RevisionService;
use App\Support\TextDiff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Version history and diff for posts, tweets and pages (JYU-553). */
class RevisionController extends Controller
{
    private const TYPES = ['posts' => Post::class, 'tweets' => Tweet::class, 'pages' => Page::class];

    public function index(RevisionIndexRequest $request, string $type, int $id, RevisionService $revisions): View
    {
        $model = $this->resolve($type, $id);
        $all = $model->revisions()->latest('id')->get();
        $filter = $request->validated('filter') ?? 'all';

        $visible = $all->filter(fn (ContentRevision $r) => match ($filter) {
            'ai' => $r->isAi(),
            'status' => $this->statusChanged($all, $r),
            default => true,
        })->values();

        $selected = $all->firstWhere('id', (int) $request->validated('rev')) ?? $visible->first() ?? $all->first();
        $previous = $selected ? $this->previous($all, $selected) : null;
        $compare = $request->validated('compare') ? $all->firstWhere('id', (int) $request->validated('compare')) : $previous;

        $paragraphs = $selected
            ? TextDiff::paragraphs((string) ($compare?->snapshot['body'] ?? ''), (string) ($selected->snapshot['body'] ?? ''))
            : [];

        return view('admin.revisions.index', [
            'type' => $type,
            'model' => $model,
            'label' => $this->label($model),
            'editUrl' => route('admin.'.$type.'.edit', $model),
            'filter' => $filter,
            'total' => $all->count(),
            'groups' => $visible->groupBy(fn ($r) => RevisionService::dayLabel($r->created_at)),
            'stats' => $all->mapWithKeys(fn ($r) => [$r->id => $revisions->stat($r, $this->previous($all, $r))]),
            'others' => $all->reject(fn ($r) => $r->id === $selected?->id)->values(),
            'selected' => $selected,
            'previous' => $previous,
            'compare' => $compare,
            'paragraphs' => $paragraphs,
            'changedParagraphs' => array_values(array_filter($paragraphs, fn ($p) => $p['type'] !== 'same')),
            'fields' => $selected ? $revisions->fieldChanges($compare?->snapshot, $selected->snapshot) : null,
        ]);
    }

    public function restore(string $type, int $id, ContentRevision $revision, RevisionService $revisions): RedirectResponse
    {
        $model = $this->resolve($type, $id);
        abort_unless($revision->revisionable_type === $model->getMorphClass() && $revision->revisionable_id === $model->getKey(), 404);

        $new = $revisions->restoreBefore($revision);
        abort_if(! $new, 422, '這是最早的版本，沒有更早的內容可以還原');

        return redirect()
            ->route('admin.revisions.index', ['type' => $type, 'id' => $id, 'rev' => $new->id])
            ->with('success', '已還原，這次還原也記成一個新版本');
    }

    private function resolve(string $type, int $id): Model
    {
        $class = self::TYPES[$type] ?? abort(404);

        // Trashed content keeps its history and can still be restored.
        return $class::withTrashed()->findOrFail($id);
    }

    private function previous($all, ContentRevision $r): ?ContentRevision
    {
        return $all->first(fn ($x) => $x->id < $r->id);
    }

    private function statusChanged($all, ContentRevision $r): bool
    {
        $prev = $this->previous($all, $r);

        return $prev !== null && ($prev->snapshot['status'] ?? null) !== ($r->snapshot['status'] ?? null);
    }

    private function label(Model $model): string
    {
        return $model instanceof Tweet
            ? Str::limit(trim(strip_tags((string) $model->body)), 40) ?: '（碎念）'
            : ($model->title ?: '（未命名）');
    }
}
