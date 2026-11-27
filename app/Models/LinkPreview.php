<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LinkPreview extends Model
{
    public const STATUS_OK = 'ok';

    public const STATUS_FAILED = 'failed';

    protected $fillable = ['url_hash', 'url', 'domain', 'title', 'status', 'fetched_at'];

    protected function casts(): array
    {
        return ['fetched_at' => 'datetime'];
    }

    public static function hashFor(string $url): string
    {
        return sha1($url);
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_OK && filled($this->title);
    }
}
