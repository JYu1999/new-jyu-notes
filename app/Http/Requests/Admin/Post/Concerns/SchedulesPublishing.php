<?php

namespace App\Http\Requests\Admin\Post\Concerns;

use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Carbon;

/**
 * Scheduled publishing fields shared by the editor's store / update requests (JYU-549).
 *
 * `publish_when` is "now" (publish when the button is pressed) or "schedule";
 * `scheduled_at` comes from a datetime-local input in the site's display timezone.
 */
trait SchedulesPublishing
{
    protected function scheduleRules(): array
    {
        return [
            'publish_when' => 'sometimes|in:now,schedule',
            'scheduled_at' => 'exclude_unless:publish_when,schedule|required|date|after:now',
        ];
    }

    protected function scheduleMessages(): array
    {
        return [
            'scheduled_at.required' => '請選擇排程發布的日期與時間',
            'scheduled_at.after' => '排程時間必須在現在之後',
        ];
    }

    /** Read the datetime-local value in the display timezone and store it as UTC. */
    protected function normalizeScheduledAt(): void
    {
        $value = $this->input('scheduled_at');
        if (! is_string($value) || $value === '') {
            return;
        }

        try {
            $at = Carbon::parse($value, (string) config('app.display_timezone', 'UTC'))->utc();
            $this->merge(['scheduled_at' => $at->toIso8601String()]);
        } catch (InvalidFormatException) {
            // Leave it as-is; the `date` rule reports it.
        }
    }
}
