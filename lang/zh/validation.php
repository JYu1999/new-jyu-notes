<?php

return [
    'required' => ':attribute 為必填',
    'string' => ':attribute 必須為字串',
    'integer' => ':attribute 必須為整數',
    'numeric' => ':attribute 必須為數字',
    'boolean' => ':attribute 必須為布林值',
    'array' => ':attribute 必須為陣列',
    'min' => [
        'numeric' => ':attribute 必須大於等於 :min',
        'string' => ':attribute 至少需要 :min 個字元',
        'array' => ':attribute 至少需要 :min 個項目',
    ],
    'max' => [
        'numeric' => ':attribute 不可大於 :max',
        'string' => ':attribute 不可超過 :max 個字元',
        'array' => ':attribute 不可超過 :max 個項目',
    ],
    'in' => '所選的 :attribute 無效',
    'distinct' => ':attribute 有重複值',
    'email' => ':attribute 必須是有效的 email',
    'date' => ':attribute 必須是有效的日期',
    'unique' => ':attribute 已被使用',
    'exists' => '選擇的 :attribute 不存在',
    'regex' => ':attribute 格式不正確',
    'not_regex' => ':attribute 格式不正確',
    'file' => ':attribute 必須是檔案',
    'mimes' => ':attribute 必須是下列檔案類型：:values',
    'image' => ':attribute 必須是圖片',
    'confirmed' => ':attribute 二次驗證不符',
    'between' => [
        'numeric' => ':attribute 必須介於 :min 到 :max 之間',
        'string' => ':attribute 長度需介於 :min 到 :max 字元之間',
    ],
];
