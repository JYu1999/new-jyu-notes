<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Tweet\CreateTranslationRequest;
use App\Http\Requests\Admin\Tweet\IndexRequest;
use App\Http\Requests\Admin\Tweet\StoreRequest;
use App\Http\Requests\Admin\Tweet\UpdateRequest;
use App\Http\Requests\Admin\Tweet\UpdateStatusRequest;
use App\Models\Tweet;
use App\Repositories\TagRepository;
use App\Repositories\TweetRepository;
use App\Services\TweetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TweetController extends Controller
{
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

    public function create(TagRepository $tags): View
    {
        return view('admin.tweets.edit', [
            'tweet' => new Tweet([
                'status' => Tweet::STATUS_DRAFT,
                'locale' => app()->getLocale(),
            ]),
            'translations' => collect(),
            'tags' => $tags->all(),
            'mode' => 'create',
        ]);
    }

    public function store(StoreRequest $request, TweetService $service): RedirectResponse
    {
        $tweet = $service->create($request->validated());
        return redirect()->route('admin.tweets.edit', $tweet)->with('success', '已建立');
    }

    public function edit(Tweet $tweet, TagRepository $tags): View
    {
        $tweet->load('tags.translations');

        return view('admin.tweets.edit', [
            'tweet' => $tweet,
            'translations' => $tweet->allTranslations()->keyBy('locale'),
            'tags' => $tags->all(),
            'mode' => 'edit',
        ]);
    }

    public function update(Tweet $tweet, UpdateRequest $request, TweetService $service): RedirectResponse
    {
        $service->update($tweet, $request->validated());
        return redirect()->route('admin.tweets.edit', $tweet)->with('success', '已更新');
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
        $service->updateStatus($tweet, $request->validated()['status']);
        return back()->with('success', '狀態已更新');
    }

    public function createTranslation(Tweet $tweet, CreateTranslationRequest $request, TweetService $service): RedirectResponse
    {
        $new = $service->createTranslation($tweet, $request->validated()['locale']);
        return redirect()->route('admin.tweets.edit', $new)
            ->with('success', '已建立新翻譯版本');
    }
}
