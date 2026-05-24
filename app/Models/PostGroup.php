<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PostGroup extends Model
{
    use HasFactory;

    protected $fillable = [];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function postByLocale(string $locale): ?Post
    {
        return $this->posts()->where('locale', $locale)->first();
    }
}
