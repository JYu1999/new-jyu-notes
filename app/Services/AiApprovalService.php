<?php

namespace App\Services;

use App\Mcp\Servers\BlogServer;
use App\Mcp\Tools\BlogTool;
use App\Models\AiActionRequest;
use App\Models\AiConnection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Approving or rejecting what an AI asked for under "需確認" (JYU-556).
 * Approving runs the queued tool call as that AI; rejecting changes nothing.
 */
class AiApprovalService
{
    /** @return array<int, array{badge: string, count: int, title: string, hint: string, url: string}> items for the admin home "需要你處理" */
    public function attentionItems(): array
    {
        return AiActionRequest::query()->with('aiConnection')->where('status', AiActionRequest::STATUS_PENDING)
            ->latest('id')->limit(5)->get()
            ->map(fn (AiActionRequest $r) => [
                'badge' => 'AI',
                'count' => 1,
                'title' => $this->headline($r),
                'hint' => $r->created_at->locale('zh_TW')->diffForHumans().' · 點進去檢查後核准或拒絕',
                'url' => route('admin.agents.requests.show', $r),
            ])->all();
    }

    /** "Claude 想發布〈…〉" */
    public function headline(AiActionRequest $r): string
    {
        return ($r->aiConnection?->name ?? 'AI').' 想'.$r->summary;
    }

    /** Button label: 核准並發布 / 核准並隱藏 / 核准 */
    public function approveLabel(AiActionRequest $r): string
    {
        return match (true) {
            $r->tool === 'request_publish' && ($r->arguments['action'] ?? null) === 'hide' => '核准並隱藏',
            $r->tool === 'request_publish' => '核准並發布',
            default => '核准',
        };
    }

    /** Run the queued call. Returns null on success, or the error message. */
    public function approve(AiActionRequest $r): ?string
    {
        abort_unless($r->status === AiActionRequest::STATUS_PENDING, 409, '這個要求已經處理過了');

        $tool = $this->tool($r->tool);
        $connection = $r->aiConnection;
        if ($connection) {
            app()->instance(AiConnection::class, $connection);
            app(RevisionService::class)->actAsAi($connection->name, $connection->user_id);
        }

        try {
            $response = $tool->runApproved($r);
            $error = $response->isError() ? (string) $response->content() : null;
        } catch (ValidationException $e) {
            $error = $e->validator->errors()->first();
        } finally {
            app()->forgetInstance(AiConnection::class);
        }

        $r->forceFill([
            'status' => $error ? AiActionRequest::STATUS_FAILED : AiActionRequest::STATUS_APPROVED,
            'error' => $error ? Str::limit($error, 1000) : null,
            'decided_at' => now(),
        ])->save();

        return $error;
    }

    public function reject(AiActionRequest $r): void
    {
        abort_unless($r->status === AiActionRequest::STATUS_PENDING, 409, '這個要求已經處理過了');

        $r->forceFill(['status' => AiActionRequest::STATUS_REJECTED, 'decided_at' => now()])->save();
    }

    private function tool(string $name): BlogTool
    {
        foreach ((new \ReflectionClass(BlogServer::class))->getDefaultProperties()['tools'] as $class) {
            $tool = app($class);
            if ($tool->name() === $name) {
                return $tool;
            }
        }

        abort(422, '找不到這個工具');
    }
}
