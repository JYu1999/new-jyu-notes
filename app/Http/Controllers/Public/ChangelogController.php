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

            if (preg_match('/^##\s+(\d{4})-(\d{2})-(\d{2})\s*$/', $line, $match) === 1) {
                $year = (int) $match[1];
                $month = (int) $match[2];
                $day = (int) $match[3];

                // Validate that the date is a real calendar date
                if (checkdate($month, $day, $year)) {
                    $currentDate = $match[1].'-'.$match[2].'-'.$match[3];
                    $groups->put($currentDate, $groups->get($currentDate, collect()));
                }

                continue;
            }

            if ($currentDate !== null && preg_match('/^-\s+(.+)$/', $line, $match) === 1) {
                $groups->put($currentDate, $groups->get($currentDate)->push((object) [
                    'title' => trim($match[1]),
                ]));
            }
        }

        return $groups->sortKeysDesc();
    }
}
