<?php

namespace App\Support;

/**
 * What an AI connection may do (JYU-554). Chosen on the consent screen and
 * checked by every MCP tool. Deleting content is never possible.
 */
final class AiPermissions
{
    public const READ = 'read';

    public const DRAFT = 'draft';

    public const ORGANIZE = 'organize';

    public const PUBLISH = 'publish';

    /** @var array<string, array{label: string, hint: ?string, default: bool}> */
    public const ALL = [
        self::READ => ['label' => '讀取所有內容', 'hint' => '文章、碎念、頁面，包含草稿', 'default' => true],
        self::DRAFT => ['label' => '建立與編輯草稿、翻譯', 'hint' => '每次修改都會留下版本，可以還原', 'default' => true],
        self::ORGANIZE => ['label' => '整理分類、標籤與媒體', 'hint' => null, 'default' => true],
        self::PUBLISH => ['label' => '要求發布或隱藏', 'hint' => '每一次都要你在後台核准', 'default' => false],
    ];

    public const DURATIONS = [90, 30, 7];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::ALL);
    }
}
