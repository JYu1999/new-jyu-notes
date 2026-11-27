<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Agent\ConnectionPolicyRequest;
use App\Http\Requests\Admin\Agent\PolicyRequest;
use App\Mcp\Servers\BlogServer;
use App\Mcp\Tools\BlogTool;
use App\Models\AiActivity;
use App\Models\AiConnection;
use App\Services\AiConnectionService;
use App\Support\AiPolicy;
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
            'activities' => AiActivity::query()->with(['aiConnection', 'subject'])->latest('id')->limit(8)->get(),
            'tools' => $this->tools($global),
        ]);
    }

    public function activity(): View
    {
        return view('admin.agents.activity', [
            'activities' => AiActivity::query()->with(['aiConnection', 'subject'])->latest('id')->paginate(50),
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
