<?php

return [
    'column_toggle' => ['heading' => '显示列'],
    'columns' => [
        'actions' => ['label' => '操作'],
        'text' => [
            'actions' => [
                'collapse_list' => '收起 :count 项',
                'expand_list' => '展开 :count 项',
            ],
            'more_list_items' => '还有 :count 项',
        ],
    ],
    'fields' => [
        'bulk_select_page' => ['label' => '勾选/取消当前页全部记录。'],
        'bulk_select_record' => ['label' => '勾选/取消记录 :key。'],
        'bulk_select_group' => ['label' => '勾选/取消分组 :title。'],
        'search' => [
            'label' => '搜索',
            'placeholder' => '搜索',
            'indicator' => '搜索',
        ],
    ],
    'summary' => [
        'heading' => '汇总',
        'subheadings' => [
            'all' => '全部 :label',
            'group' => ':group 汇总',
            'page' => '当前页',
        ],
        'summarizers' => [
            'average' => ['label' => '平均值'],
            'count' => ['label' => '数量'],
            'sum' => ['label' => '总和'],
        ],
    ],
    'actions' => [
        'disable_reordering' => ['label' => '完成排序'],
        'enable_reordering' => ['label' => '拖拽排序'],
        'filter' => ['label' => '筛选'],
        'group' => ['label' => '分组'],
        'open_bulk_actions' => ['label' => '批量操作'],
        'toggle_columns' => ['label' => '显示列'],
    ],
    'empty' => [
        'heading' => '暂无 :model',
        'description' => '点击“新建”开始录入 :model。',
    ],
    'filters' => [
        'actions' => [
            'apply' => ['label' => '应用筛选'],
            'remove' => ['label' => '移除筛选'],
            'remove_all' => ['label' => '清空筛选', 'tooltip' => '清空筛选'],
            'reset' => ['label' => '重置'],
        ],
        'heading' => '筛选条件',
        'indicator' => '当前筛选',
        'multi_select' => ['placeholder' => '全部'],
        'select' => ['placeholder' => '全部'],
    ],
    'grouping' => [
        'fields' => [
            'group' => ['label' => '按字段分组', 'placeholder' => '选择分组字段'],
            'direction' => [
                'label' => '分组顺序',
                'options' => [
                    'asc' => '升序',
                    'desc' => '降序',
                ],
            ],
        ],
    ],
    'reorder_indicator' => '拖拽记录调整顺序。',
    'selection_indicator' => [
        'selected_count' => '已选择 1 条记录|已选择 :count 条记录',
        'actions' => [
            'select_all' => ['label' => '选择全部 :count 条'],
            'deselect_all' => ['label' => '取消全选'],
        ],
    ],
    'sorting' => [
        'fields' => [
            'column' => ['label' => '排序字段'],
            'direction' => [
                'label' => '排序方向',
                'options' => [
                    'asc' => '升序',
                    'desc' => '降序',
                ],
            ],
        ],
    ],
];
