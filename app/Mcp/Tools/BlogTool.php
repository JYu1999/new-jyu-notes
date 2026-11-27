<?php

namespace App\Mcp\Tools;

use App\Models\AiActionRequest;
use App\Models\AiActivity;
use App\Models\AiConnection;
use App\Models\ContentRevision;
use App\Models\Page;
use App\Models\Post;
use App\Models\TagTranslation;
use App\Models\Tweet;
use App\Support\AiPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Shared behaviour of the blog's MCP tools (JYU-554 / JYU-555).
 *
 * Each tool belongs to a policy action (App\Support\AiPolicy). For the calling
 * connection that action is allowed (the tool runs), needs confirmation (the call
 * is queued for the author, see JYU-556) or denied (the tool isn't even listed).
 * Every call is written to the activity log.
 */
abstract class BlogTool extends Tool
{
    public const TYPES = ['post' => Post::class, 'tweet' => Tweet::class, 'page' => Page::class];

    /** Policy action key, see AiPolicy::ACTIONS. */
    protected string $action = 'read';

    /** The content a run touched, for the activity log. */
    protected ?Model $subject = null;

    /** Do the work. Called directly, or later for an approved request. */
    abstract protected function run(Request $request): Response;

    /** One-line description for the activity log and the approval queue. */
    abstract protected function describe(array $args): string;

    public function handle(Request $request): Response
    {
        $connection = $this->connection();
        $mode = $connection ? AiPolicy::effective($connection, $this->action) : AiPolicy::DENY;

        if ($mode === AiPolicy::DENY) {
            return Response::error('這個 AI 連線不能「'.AiPolicy::ACTIONS[$this->action]['name'].'」。');
        }

        if ($mode === AiPolicy::ASK) {
            return $this->queue($connection, $request->all());
        }

        return $this->runAndLog($request);
    }

    public function shouldRegister(): bool
    {
        $connection = $this->connection();

        return $connection !== null && AiPolicy::effective($connection, $this->action) !== AiPolicy::DENY;
    }

    /** Run a request the author approved (JYU-556). */
    public function runApproved(AiActionRequest $pending): Response
    {
        return $this->runAndLog(new Request($pending->arguments ?? []), $pending);
    }

    public function policyAction(): string
    {
        return $this->action;
    }

    private function runAndLog(Request $request, ?AiActionRequest $pending = null): Response
    {
        $this->subject = null;
        $startedAt = now()->subSecond();
        $response = $this->run($request);
        $args = $request->all();
        $this->subject ??= isset($args['type'], $args['id']) ? $this->find((string) $args['type'], (int) $args['id']) : null;

        $revision = $this->subject && method_exists($this->subject, 'revisions')
            ? $this->subject->revisions()->where('actor', ContentRevision::ACTOR_AI)->where('updated_at', '>=', $startedAt)->latest('id')->first()
            : null;

        AiActivity::create([
            'ai_connection_id' => $this->connection()?->id,
            'tool' => $this->name(),
            'summary' => Str::limit($this->describe($args), 250),
            'status' => $response->isError() ? 'failed' : 'done',
            'subject_type' => $this->subject?->getMorphClass(),
            'subject_id' => $this->subject?->getKey(),
            'content_revision_id' => $revision?->id,
            'ai_action_request_id' => $pending?->id,
        ]);

        return $response;
    }

    private function queue(AiConnection $connection, array $args): Response
    {
        $subject = isset($args['type'], $args['id']) ? $this->find((string) $args['type'], (int) $args['id']) : null;

        $pending = AiActionRequest::query()->firstOrCreate([
            'ai_connection_id' => $connection->id,
            'tool' => $this->name(),
            'status' => AiActionRequest::STATUS_PENDING,
            'publishable_type' => $subject?->getMorphClass(),
            'publishable_id' => $subject?->getKey(),
            'summary' => Str::limit($this->describe($args), 250),
        ], [
            'action' => $this->action,
            'arguments' => $args,
            'note' => $args['note'] ?? null,
        ]);

        if ($pending->wasRecentlyCreated) {
            AiActivity::create([
                'ai_connection_id' => $connection->id,
                'tool' => $this->name(),
                'summary' => '要求'.$pending->summary,
                'status' => 'queued',
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'ai_action_request_id' => $pending->id,
            ]);
        }

        return Response::json([
            'status' => 'pending',
            'request_id' => $pending->id,
            'message' => '這個動作需要作者確認，已送出；作者在後台核准後才會執行。',
        ]);
    }

    protected function connection(): ?AiConnection
    {
        return app()->bound(AiConnection::class) ? app(AiConnection::class) : null;
    }

    /** "〈title〉" for content named in the arguments. */
    protected function label(array $args): string
    {
        $m = isset($args['type'], $args['id']) ? $this->find((string) $args['type'], (int) $args['id']) : null;
        if (! $m) {
            return '內容';
        }

        return ($m instanceof Tweet ? '碎念' : ($m instanceof Page ? '頁面' : '文章'))
            .'〈'.($m instanceof Tweet ? Str::limit(strip_tags((string) $m->body), 24) : Str::limit((string) $m->title, 40)).'〉'
            .($m->locale !== 'zh' ? ' '.strtoupper($m->locale).' 版' : '');
    }

    protected function find(string $type, int $id): ?Model
    {
        $class = self::TYPES[$type] ?? null;

        return $class ? $class::query()->find($id) : null;
    }

    /** @return array<string, mixed> */
    protected function summary(Model $m): array
    {
        $type = array_search($m::class, self::TYPES, true);

        return array_filter([
            'type' => $type,
            'id' => $m->id,
            'locale' => $m->locale,
            'status' => $m->status,
            'title' => $m instanceof Tweet ? Str::limit(strip_tags((string) $m->body), 60) : $m->title,
            'slug' => $m->slug ?? null,
            'updated_at' => $m->updated_at?->toIso8601String(),
            'url' => $this->publicUrl($m),
        ], fn ($v) => $v !== null);
    }

    /** @return array<string, mixed> */
    protected function detail(Model $m): array
    {
        $groupKey = match (true) {
            $m instanceof Post => 'post_group_id',
            $m instanceof Tweet => 'tweet_group_id',
            default => 'page_group_id',
        };

        return $this->summary($m) + array_filter([
            'body' => (string) $m->body,
            'excerpt' => $m instanceof Post ? $m->excerpt : null,
            'tags' => method_exists($m, 'tags') ? $m->tags()->with('translations')->get()->map(fn ($t) => $t->name('zh'))->values()->all() : null,
            'categories' => $m instanceof Post ? $m->categories()->with('translations')->get()->map(fn ($c) => $c->name('zh'))->values()->all() : null,
            'media' => $m instanceof Tweet ? $m->media : null,
            'translations' => $m::query()->where($groupKey, $m->{$groupKey})->where('id', '!=', $m->id)
                ->get(['id', 'locale', 'status'])->map(fn ($t) => ['id' => $t->id, 'locale' => $t->locale, 'status' => $t->status])->values()->all(),
        ], fn ($v) => $v !== null);
    }

    protected function publicUrl(Model $m): ?string
    {
        if ($m->status !== 'published') {
            return null;
        }

        return match (true) {
            $m instanceof Post => route('public.posts.show', [$m->locale, $m->slug]),
            $m instanceof Tweet => route('public.tweets.show', [$m->locale, $m->id]),
            default => url('/'.$m->locale.'/'.$m->slug),
        };
    }

    /** Existing tag ids for the given names (any language). */
    protected function tagIds(array $names): array
    {
        return TagTranslation::query()->whereIn('name', array_map('trim', $names))->distinct()->pluck('tag_id')->all();
    }
}
