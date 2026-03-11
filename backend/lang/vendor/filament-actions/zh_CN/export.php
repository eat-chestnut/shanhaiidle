<?php

return [
    'label' => '导出 :label',
    'modal' => [
        'heading' => '导出 :label',
        'form' => [
            'columns' => [
                'label' => '导出字段',
                'form' => [
                    'is_enabled' => ['label' => '启用 :column'],
                    'label' => ['label' => ':column 显示名'],
                ],
            ],
        ],
        'actions' => [
            'export' => ['label' => '开始导出'],
        ],
    ],
    'notifications' => [
        'completed' => [
            'title' => '导出完成',
            'actions' => [
                'download_csv' => ['label' => '下载 CSV'],
                'download_xlsx' => ['label' => '下载 XLSX'],
            ],
        ],
        'max_rows' => [
            'title' => '导出数量超限',
            'body' => '一次最多导出 1 条记录。|一次最多导出 :count 条记录。',
        ],
        'started' => [
            'title' => '已开始导出',
            'body' => '导出已开始，后台将处理 1 条记录。完成后会通知你下载。|导出已开始，后台将处理 :count 条记录。完成后会通知你下载。',
        ],
    ],
    'file_name' => 'export-:export_id-:model',
];
