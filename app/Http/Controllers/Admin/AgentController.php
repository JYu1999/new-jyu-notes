<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Agent\ConnectionPolicyRequest;
use App\Http\Requests\Admin\Agent\PolicyRequest;
use App\Mcp\Servers\BlogServer;
use App\Mcp\Tools\BlogTool;
use App\Models\AiActionRequest;
use App\Models\AiActivity;
use App\Models\AiConnection;
use App\Services\AiApprovalService;
use App\Services\AiConnectionService;
use App\Support\AiPolicy;
use App\Support\TextDiff;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Admin "AI 連線" (JYU-554 / JYU-555): connect, review and limit AIs. */
class AgentController extends Controller
{
    public function index(): View
    {
        $global = AiPolicy::global();

        return view('admin.agents.index', [
            'mcpUrl' => url('/mcp'),
            'connections' => AiConnection::query()->with('client')->whereNull('revoked_at')->latest()->get(),
            'global' => $global,
            'activities' => AiActivity::query()->with(['aiConnection', 'subject', 'actionRequest'])->latest('id')->limit(8)->get(),
            'tools' => $this->tools($global),
            'pending' => AiActionRequest::query()->with(['aiConnection', 'publishable'])
                ->where('status', AiActionRequest::STATUS_PENDING)->latest('id')->get(),
        ]);
    }

    /** One queued request (JYU-556): what the AI wants to do, with the proposed text diffed against the current one. */
    public function showRequest(AiActionRequest $actionRequest, AiApprovalService $approvals): View
    {
        $args = $actionRequest->arguments ?? [];
        $subject = $actionRequest->publishable;
        $types = ['post' => 'posts', 'tweet' => 'tweets', 'page' => 'pages'];

        return view('admin.agents.request', [
            'r' => $actionRequest,
            'headline' => $approvals->headline($actionRequest),
            'approveLabel' => $approvals->approveLabel($actionRequest),
            'subject' => $subject,
            'historyUrl' => $subject && isset($types[$actionRequest->publishable_type])
                ? route('admin.revisions.index', ['type' => $types[$actionRequest->publishable_type], 'id' => $subject->getKey()]) : null,
            'titleChange' => isset($args['title']) && $args['title'] !== ($subject->title ?? null) ? [$subject->title ?? '', $args['title']] : null,
            'paragraphs' => isset($args['body'])
                ? array_values(array_filter(TextDiff::paragraphs($actionRequest->tool === 'update_content' ? (string) ($subject->body ?? '') : '', (string) $args['body']), fn ($p) => $p['type'] !== 'same'))
                : null,
            'args' => collect($args)->except(['body', 'title', 'type', 'id', 'note', 'data'])->all(),
        ]);
    }

    public function approve(AiActionRequest $actionRequest, AiApprovalService $approvals): RedirectResponse
    {
        $error = $approvals->approve($actionRequest);

        return redirect()->route('admin.agents.index')->with($error ? 'error' : 'success', $error
            ? '執行失敗：'.$error
            : '已核准並執行：'.$actionRequest->summary);
    }

    public function reject(AiActionRequest $actionRequest, AiApprovalService $approvals): RedirectResponse
    {
        $approvals->reject($actionRequest);

        return redirect()->route('admin.agents.index')->with('success', '已拒絕，內容維持原狀');
    }

    public function activity(): View
    {
        return view('admin.agents.activity', [
            'activities' => AiActivity::query()->with(['aiConnection', 'subject', 'actionRequest'])->latest('id')->paginate(50),
        ]);
    }

    public function updatePolicy(PolicyRequest $request): RedirectResponse
    {
        AiPolicy::saveGlobal($request->validated('policy'));

        return back()->with('success', '已更新 AI 可以做的事');
    }

    public function updateConnectionPolicy(AiConnection $connection, ConnectionPolicyRequest $request): RedirectResponse
    {
        $connection->update(['policy' => array_merge($connection->policy ?? [], $request->validated('policy'))]);

        return back()->with('success', '已更新「'.$connection->name.'」的權限');
    }

    public function revoke(AiConnection $connection, AiConnectionService $service): RedirectResponse
    {
        $service->revoke($connection);

        return back()->with('success', '已中斷「'.$connection->name.'」，它的授權立刻失效');
    }

    /** @return list<array{name: string, kind: string}> */
    private function tools(array $global): array
    {
        $classes = (new \ReflectionClass(BlogServer::class))->getDefaultProperties()['tools'];

        return collect($classes)->map(function (string $class) use ($global) {
            /** @var BlogTool $tool */
            $tool = app($class);
            $action = $tool->policyAction();

            return [
                'name' => $tool->name(),
                'kind' => match (true) {
                    $action === 'read' => '讀',
                    $global[$action] === AiPolicy::ASK => '需確認',
                    $global[$action] === AiPolicy::DENY => '禁止',
                    default => '寫',
                },
            ];
        })->all();
    }
}
