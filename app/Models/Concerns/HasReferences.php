<?php

namespace App\Models\Concerns;

use App\Models\Reference;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

trait HasReferences
{
    /** References where this model is the source (content it mentions). */
    public function outgoingReferences(): MorphMany
    {
        return $this->morphMany(Reference::class, 'source');
    }

    /** References where this model is the target (content mentioning it). */
    public function incomingReferences(): MorphMany
    {
        return $this->morphMany(Reference::class, 'target');
    }

    /**
     * Mixed-type source models that publicly mention this model,
     * newest first. Both Post and Tweet expose status + published_at.
     *
     * @return Collection<int, \Illuminate\Database\Eloquent\Model>
     */
    public function publishedBacklinks(): Collection
    {
        return $this->incomingReferences()
            ->with('source')
            ->get()
            ->map(fn (Reference $r) => $r->source)
            ->filter()
            ->filter(fn ($m) => $m->status === 'published')
            ->sortByDesc('published_at')
            ->values();
    }
}
