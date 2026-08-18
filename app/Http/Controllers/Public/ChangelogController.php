<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class ChangelogController extends Controller
{
    public function index(): View
    {
        return view('public.changelog', [
            'groups' => $this->changelogGrouped(),
        ]);
    }

    /**
     * Parse CHANGELOG.md into date-grouped entries. Date sections are
     * re-sorted newest-first regardless of their physical order in the
     * file; bullets within a section keep the order they were written in.
     * Any heading-shaped line (starts with #) that isn't a valid
     * ## YYYY-MM-DD with a real calendar date resets the current section
     * to null — this prevents calendar-invalid headings from reaching
     * Carbon::parse() in the view and crashing the page, and also prevents
     * any malformed heading's trailing bullets from being silently
     * misattributed to the previous valid section.
     *
     * @return Collection<string, Collection<int, object{title: string}>>
     */
    private function changelogGrouped(): Collection
    {
        $path = config('changelog.path');

        if (! File::exists($path)) {
            return collect();
        }

        $groups = collect();
        $currentDate = null;

        foreach (preg_split('/\R/', File::get($path)) as $line) {
            $line = rtrim($line);

            // 以 # 開頭來判斷標題
            if (str_starts_with($line, '#')) {
                // 日期標題行格式為 ## YYYY-MM-DD
                if (preg_match('/^##\s+(\d{4})-(\d{2})-(\d{2})\s*$/', $line, $match) === 1
                    && checkdate((int) $match[2], (int) $match[3], (int) $match[1])) {  // 數字格式對，但是日期是否為真實存在
                    $currentDate = $match[1].'-'.$match[2].'-'.$match[3];
                    $groups->put($currentDate, $groups->get($currentDate, collect()));
                } else {
                    $currentDate = null; // 不符合上面的日期標題行的格式會是無效的日期
                }

                continue;
            }
            // 有效的日期與以- 為開頭，這兩個條件要同時存在
            if ($currentDate !== null && preg_match('/^-\s+(.+)$/', $line, $match) === 1) {
                $groups->put($currentDate, $groups->get($currentDate)->push((object) [
                    'title' => trim($match[1]),
                ]));
            }
        }

        // 把有效日期但沒有內容的區塊移除，按照新到舊的順序排列
        return $groups->reject(fn (Collection $items) => $items->isEmpty())->sortKeysDesc();
    }
}
