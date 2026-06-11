<?php

namespace App\Support;

class PostReferenceExtractor
{
    /**
     * 從 body 抽取內部文章連結，回傳去重後的 (locale, slug) 陣列。
     *
     * 比對 /{locale}/posts/{slug}：要求 locale 前綴 + 單段 slug + 可選結尾斜線。
     * 因此自動排除 /storage/imports/posts/.../image.png 與 /posts/{{ 這類非文章連結。
     *
     * @return array<int, array{locale: string, slug: string}>
     */
    public function extract(string $body): array
    {
        $pattern = '#/(zh|en|ja|vi|id)/posts/([A-Za-z0-9_-]+)/?#';

        if (! preg_match_all($pattern, $body, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $result = [];
        $seen = [];
        foreach ($matches as $m) {
            $key = $m[1].'/'.$m[2];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = ['locale' => $m[1], 'slug' => $m[2]];
        }

        return $result;
    }
}
