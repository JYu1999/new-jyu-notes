<?php

namespace App\Support;

use App\Models\AiConnection;
use Illuminate\Support\Facades\DB;

/**
 * Allow / ask / deny for each kind of AI action (JYU-555).
 *
 * The global setting applies to every connection; a connection can only be made
 * stricter. On top of that, a connection only gets actions covered by the
 * permissions ticked on the consent screen (JYU-554). Moving content to the
 * trash is always denied: AIs never delete.
 */
final class AiPolicy
{
    public const ALLOW = 'allow';

    public const ASK = 'ask';

    public const DENY = 'deny';

    public const MODE_LABELS = [self::ALLOW => '允許', self::ASK => '需確認', self::DENY => '禁止'];

    /** @var array<string, array{name: string, desc: string, grant: ?string, default: string, modes: list<string>}> */
    public const ACTIONS = [
        'read' => ['name' => '讀取所有內容', 'desc' => '包含草稿與隱藏的內容', 'grant' => AiPermissions::READ, 'default' => self::ALLOW, 'modes' => [self::ALLOW, self::DENY]],
        'draft' => ['name' => '建立與編輯草稿', 'desc' => '已發布的文章被修改時，舊內容會留在版本紀錄', 'grant' => AiPermissions::DRAFT, 'default' => self::ALLOW, 'modes' => [self::ALLOW, self::ASK, self::DENY]],
        'translate' => ['name' => '建立翻譯版本', 'desc' => '只能新增或更新非原文的語言', 'grant' => AiPermissions::DRAFT, 'default' => self::ALLOW, 'modes' => [self::ALLOW, self::ASK, self::DENY]],
        'taxonomy' => ['name' => '管理分類與標籤', 'desc' => '', 'grant' => AiPermissions::ORGANIZE, 'default' => self::ALLOW, 'modes' => [self::ALLOW, self::ASK, self::DENY]],
        'media' => ['name' => '上傳媒體', 'desc' => '存到媒體庫', 'grant' => AiPermissions::ORGANIZE, 'default' => self::ALLOW, 'modes' => [self::ALLOW, self::ASK, self::DENY]],
        'publish' => ['name' => '發布、隱藏', 'desc' => '改變讀者看得到的內容', 'grant' => AiPermissions::PUBLISH, 'default' => self::ASK, 'modes' => [self::ALLOW, self::ASK, self::DENY]],
        'trash' => ['name' => '移到垃圾桶', 'desc' => 'AI 不能刪除內容', 'grant' => null, 'default' => self::DENY, 'modes' => [self::DENY]],
    ];

    private const STRICTNESS = [self::ALLOW => 0, self::ASK => 1, self::DENY => 2];

    /** @return array<string, string> action => mode */
    public static function global(): array
    {
        $saved = DB::table('ai_policies')->pluck('mode', 'action')->all();

        return collect(self::ACTIONS)->mapWithKeys(fn ($a, $key) => [
            $key => in_array($saved[$key] ?? null, $a['modes'], true) ? $saved[$key] : $a['default'],
        ])->all();
    }

    /** @param array<string, string> $modes */
    public static function saveGlobal(array $modes): void
    {
        foreach ($modes as $action => $mode) {
            if (isset(self::ACTIONS[$action]) && in_array($mode, self::ACTIONS[$action]['modes'], true)) {
                DB::table('ai_policies')->updateOrInsert(['action' => $action], ['mode' => $mode, 'updated_at' => now(), 'created_at' => now()]);
            }
        }
    }

    /** What this connection may do for an action: the stricter of global and its own setting, and deny without the grant. */
    public static function effective(AiConnection $connection, string $action, ?array $global = null): string
    {
        $def = self::ACTIONS[$action] ?? null;
        if (! $def || ($def['grant'] && ! $connection->allows($def['grant'])) || ! $def['grant']) {
            return self::DENY;
        }

        $global ??= self::global();

        return self::stricter($global[$action], $connection->policy[$action] ?? self::ALLOW);
    }

    /** Modes a connection may pick for an action: the global one or stricter. */
    public static function connectionModes(string $action, ?array $global = null): array
    {
        $global ??= self::global();

        return array_values(array_filter(self::ACTIONS[$action]['modes'],
            fn ($m) => self::STRICTNESS[$m] >= self::STRICTNESS[$global[$action]]));
    }

    public static function stricter(string $a, string $b): string
    {
        return self::STRICTNESS[$a] >= self::STRICTNESS[$b] ? $a : $b;
    }
}
