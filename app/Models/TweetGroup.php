<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TweetGroup extends Model
{
    use HasFactory;

    protected $fillable = [];

    public function tweets(): HasMany
    {
        return $this->hasMany(Tweet::class);
    }

    public function tweetByLocale(string $locale): ?Tweet
    {
        return $this->tweets()->where('locale', $locale)->first();
    }
}
