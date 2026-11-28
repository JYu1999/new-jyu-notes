<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Tweet\AutosaveRequest;
use App\Http\Requests\Admin\Tweet\CreateTranslationRequest;
use App\Http\Requests\Admin\Tweet\IndexRequest;
use App\Http\Requests\Admin\Tweet\StoreRequest;
use App\Http\Requests\Admin\Tweet\UpdateRequest;
use App\Http\Requests\Admin\Tweet\UpdateStatusRequest;
use App\Models\Tweet;
use App\Repositories\TagRepository;
use App\Repositories\TweetRepository;
use App\Services\TweetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TweetController extends Controller
{
    /** Where the composer may send me back to: redirect_to value => route name. */
    private const COMPOSER_ORIGINS = [
        'dashboard' => 'admin.dashboard',
        'drafts' => 'admin.drafts.index',
        'tweets' => 'admin.tweets.index',
    ];

    public function index(IndexRequest $request, TweetRepository $repo): View
    {
        $params = $request->validated();
        $data = [
            'tweets' => $repo->adminPaginate(
                status: $params['status'] ?? null,
                locale: $params['locale'] ?? null,
                search: $params['q'] ?? null,
            ),
            'counts' => $repo->countsByStatus(),
            'currentStatus' => $params['status'] ?? 'all',
            'currentLocale' => $params['locale'] ?? null,
            'currentSearch' => $params['q'] ?? '',
        ];

        if ($request->boolean('partial')) {
            return view('admin.tweets._table', $data);
        }

        return view('admin.tweets.index', $data);
    }

    public function store(StoreRequest $request, TweetService $service): RedirectResponse
    {
        $tweet = $service->create($request->safe()->except('redirect_to'));

        $message = $tweet->status === Tweet::STATUS_PUBLISHED ? '已發布' : '已存草稿';

        return $this->backToComposerOrigin($request->validated('redirect_to'), $message)
            ?? redirect()->route('admin.tweets.edit', $tweet)->with('success', '已建立');
    }

    /**
     * Editing happens in the composer too (JYU-567). Drafts keep autosaving;
     * live tweets only change when I press 儲存. Opened from anywhere but the
     * dashboard or the drafts page, it goes back to the tweet list.
     */
    public function edit(Request $request, Tweet $tweet, TagRepository $tags): View
    {
        $tweet->load('tags');
        $origin = $this->composerOrigin($request, default: 'tweets');

        return view('admin.tweets.compose', [
            'tweet' => $tweet,
            'translations' => $tweet->allTranslations()->keyBy('locale'),
            'tags' => $tags->all(),
            'locale' => $tweet->locale,
            'origin' => $origin,
            'cancelUrl' => route(self::COMPOSER_ORIGINS[$origin]),
        ]);
    }

    public function update(Tweet $tweet, UpdateRequest $request, TweetService $service): RedirectResponse
    {
        $wasDraft = $tweet->status === Tweet::STATUS_DRAFT;
        $tweet = $service->update($tweet, $request->safe()->except(['redirect_to', 'tags_sent']));
        $message = match (true) {
            $tweet->status === Tweet::STATUS_DRAFT => '已存草稿',
            $wasDraft && $tweet->status === Tweet::STATUS_PUBLISHED => '已發布',
            default => '已更新',
        };

        return $this->backToComposerOrigin($request->validated('redirect_to'), $message)
            ?? redirect()->route('admin.tweets.edit', $tweet)->with('success', '已更新');
    }

    /** `?from=` names the page the composer was opened from; anything unknown falls back to $default. */
    private function composerOrigin(Request $request, string $default): string
    {
        $from = $request->query('from');

        return is_string($from) && isset(self::COMPOSER_ORIGINS[$from]) ? $from : $default;
    }

    /**
     * The composer and the quick tweet box send me back where I came from
     * (JYU-542, JYU-563); other forms keep returning to the edit page.
     */
    private function backToComposerOrigin(?string $redirectTo, string $message): ?RedirectResponse
    {
        $route = self::COMPOSER_ORIGINS[$redirectTo] ?? null;
        if ($route === null) {
            return null;
        }

        return redirect()->route($route)->with('success', $message);
    }

    /**
     * Full-screen composer for new tweets (JYU-551), on desktop too since JYU-563.
     * `?from=tweets` sends 取消 / 發布 back to the tweet list instead of the dashboard.
     */
    public function compose(Request $request, TagRepository $tags): View
    {
        $origin = $this->composerOrigin($request, default: 'dashboard');

        return view('admin.tweets.compose', [
            'tweet' => null,
            'tags' => $tags->all(),
            'locale' => app()->getLocale(),
            'origin' => $origin,
            'cancelUrl' => route(self::COMPOSER_ORIGINS[$origin]),
        ]);
    }

    /**
     * Composer autosave (JYU-551): the first save creates a draft, later saves
     * update it. Tweets that are already live are never autosaved.
     */
    public function autosave(AutosaveRequest $request, TweetService $service): JsonResponse
    {
        $data = collect($request->validated())->except('tweet_id')->all();
        $tweet = $request->filled('tweet_id') ? Tweet::findOrFail($request->validated('tweet_id')) : null;

        if ($tweet && $tweet->status !== Tweet::STATUS_DRAFT) {
            return response()->json(['saved' => false, 'reason' => 'not_draft'], 409);
        }
        if (blank($data['body'] ?? null) && empty($data['media'])) {
            return response()->json(['saved' => false, 'reason' => 'empty'], 422);
        }

        $data['body'] = (string) ($data['body'] ?? '');
        $data['media'] ??= [];
        // The composer always sends its whole state: no tags means none.
        $data['tag_ids'] ??= [];
        if ($tweet) {
            unset($data['locale']);
            $tweet = $service->update($tweet, $data);
        } else {
            $tweet = $service->create($data + ['status' => Tweet::STATUS_DRAFT]);
        }

        return response()->json([
            'saved' => true,
            'id' => $tweet->id,
            'saved_at' => now()->toIso8601String(),
            'update_url' => route('admin.tweets.update', $tweet),
        ]);
    }

    public function destroy(Tweet $tweet, TweetService $service): RedirectResponse
    {
        $service->softDelete($tweet);

        return redirect()->route('admin.tweets.index')->with('success', '已移至垃圾桶');
    }

    public function restore(int $id, TweetService $service): RedirectResponse
    {
        $tweet = Tweet::withTrashed()->findOrFail($id);
        $service->restore($tweet);

        return redirect()->route('admin.tweets.index')->with('success', '已還原');
    }

    public function updateStatus(Tweet $tweet, UpdateStatusRequest $request, TweetService $service): RedirectResponse
    {
        $wasHidden = $tweet->status === Tweet::STATUS_HIDDEN;
        $status = $request->validated()['status'];
        $service->updateStatus($tweet, $status);

        // The tweet list hides and unhides from each row (JYU-567).
        $message = match (true) {
            $status === Tweet::STATUS_HIDDEN => '已隱藏',
            $wasHidden && $status === Tweet::STATUS_PUBLISHED => '已取消隱藏',
            default => '狀態已更新',
        };

        return back()->with('success', $message);
    }

    public function createTranslation(Tweet $tweet, CreateTranslationRequest $request, TweetService $service): RedirectResponse
    {
        $new = $service->createTranslation($tweet, $request->validated()['locale']);

        return redirect()->route('admin.tweets.edit', $new)
            ->with('success', '已建立新翻譯版本');
    }
}
