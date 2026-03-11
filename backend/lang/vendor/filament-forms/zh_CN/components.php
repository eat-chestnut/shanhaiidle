<?php

return [
    'builder' => [
        'actions' => [
            'clone' => ['label' => '复制'],
            'add' => ['label' => '添加到 :label', 'modal' => ['heading' => '添加到 :label', 'actions' => ['add' => ['label' => '添加']]]],
            'add_between' => ['label' => '插入到中间', 'modal' => ['heading' => '添加到 :label', 'actions' => ['add' => ['label' => '添加']]]],
            'delete' => ['label' => '删除'],
            'edit' => ['label' => '编辑', 'modal' => ['heading' => '编辑区块', 'actions' => ['save' => ['label' => '保存修改']]]],
            'reorder' => ['label' => '移动'],
            'move_down' => ['label' => '下移'],
            'move_up' => ['label' => '上移'],
            'collapse' => ['label' => '收起'],
            'expand' => ['label' => '展开'],
            'collapse_all' => ['label' => '全部收起'],
            'expand_all' => ['label' => '全部展开'],
        ],
    ],
    'checkbox_list' => [
        'actions' => [
            'deselect_all' => ['label' => '取消全选'],
            'select_all' => ['label' => '全选'],
        ],
    ],
];
