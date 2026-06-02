<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cover_image_path' => $this->cover_image_path,
            'sort_method' => $this->sort_method,
            'translations' => $this->translations->map(fn ($t) => [
                'locale' => $t->locale,
                'name' => $t->name,
                'slug' => $t->slug,
                'description' => $t->description,
            ])->all(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
