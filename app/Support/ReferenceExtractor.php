<?php

namespace App\Support;

use App\Models\Post;

class ReferenceExtractor
{
    /**
     * 從 body 抽取內部 post / tweet 連結,回傳去重後的條目。
     *
     * post:  /{locale}/posts/{slug}   → ['type' => 'post', 'locale', 'slug']
     * tweet: /{locale}/tweets/{id}    → ['type' => 'tweet', 'id' => int]
     *
     * @return array<int, array{type: string, locale?: string, slug?: string, id?: int}>
     */
    public function extract(string $body): array
    {
        $locales = implode('|', Post::SUPPORTED_LOCALES);

        $result = [];
        $seen = [];

        // Posts
        if (preg_match_all("#/({$locales})/posts/([A-Za-z0-9_-]+)/?#", $body, $m, PREG_SET_ORDER)) {
            foreach ($m as $row) {
                $key = "post:{$row[1]}/{$row[2]}";
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $result[] = ['type' => 'post', 'locale' => $row[1], 'slug' => $row[2]];
            }
        }

        // Tweets (numeric id; locale prefix required but resolution is by id)
        if (preg_match_all("#/({$locales})/tweets/(\d+)/?#", $body, $m, PREG_SET_ORDER)) {
            foreach ($m as $row) {
                $id = (int) $row[2];
                $key = "tweet:{$id}";
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $result[] = ['type' => 'tweet', 'id' => $id];
            }
        }

        return $result;
    }
}
