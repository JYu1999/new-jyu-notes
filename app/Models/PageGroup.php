<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PageGroup extends Model
{
    use HasFactory;

    protected $fillable = [];

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function pageByLocale(string $locale): ?Page
    {
        return $this->pages()->where('locale', $locale)->first();
    }
}
