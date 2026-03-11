<?php

return [
    'single' => [
        'label' => '删除',
        'modal' => [
            'heading' => '删除 :label',
            'actions' => [
                'delete' => ['label' => '确认删除'],
            ],
        ],
        'notifications' => [
            'deleted' => ['title' => '删除成功'],
        ],
    ],
    'multiple' => [
        'label' => '删除所选',
        'modal' => [
            'heading' => '删除所选 :label',
            'actions' => [
                'delete' => ['label' => '确认删除'],
            ],
        ],
        'notifications' => [
            'deleted' => ['title' => '删除成功'],
        ],
    ],
];
