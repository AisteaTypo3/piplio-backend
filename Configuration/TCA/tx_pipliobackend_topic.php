<?php
return [
    'ctrl' => [
        'title'            => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.topic.title',
        'label'            => 'title',
        'label_alt'        => 'topic_id',
        'label_alt_force'  => true,
        'tstamp'           => 'tstamp',
        'crdate'           => 'crdate',
        'delete'           => 'deleted',
        'enablecolumns'    => ['disabled' => 'hidden'],
        'security'         => [
            'ignorePageTypeRestriction' => true,
        ],
        'searchFields'     => 'topic_id,title,subtitle',
        'iconfile'         => 'EXT:piplio_backend/Resources/Public/Icons/Extension.svg',
        'default_sortby'   => 'sort_order ASC',
    ],

    'columns' => [
        'hidden' => [
            'label'  => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.topic.hidden',
            'config' => ['type' => 'check', 'renderType' => 'checkboxToggle'],
        ],

        'topic_id' => [
            'label'  => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.topic.topicId',
            'config' => [
                'type'     => 'input',
                'size'     => 30,
                'required' => true,
                'eval'     => 'trim,unique',
            ],
        ],

        'title' => [
            'label'  => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.topic.topicTitle',
            'config' => [
                'type'     => 'input',
                'size'     => 40,
                'required' => true,
                'eval'     => 'trim',
            ],
        ],

        'subtitle' => [
            'label'  => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.topic.subtitle',
            'config' => [
                'type' => 'input',
                'size' => 40,
                'eval' => 'trim',
            ],
        ],

        'color_key' => [
            'label'  => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.topic.colorKey',
            'config' => [
                'type'       => 'select',
                'renderType' => 'selectSingle',
                'items'      => [
                    ['label' => 'numbers20', 'value' => 'numbers20'],
                    ['label' => 'addition20', 'value' => 'addition20'],
                    ['label' => 'subtraction20', 'value' => 'subtraction20'],
                    ['label' => 'numbers100', 'value' => 'numbers100'],
                    ['label' => 'addition100', 'value' => 'addition100'],
                    ['label' => 'subtraction100', 'value' => 'subtraction100'],
                    ['label' => 'make100', 'value' => 'make100'],
                    ['label' => 'bridge100', 'value' => 'bridge100'],
                    ['label' => 'times2_5_10', 'value' => 'times2_5_10'],
                    ['label' => 'times3_4', 'value' => 'times3_4'],
                    ['label' => 'division_intro', 'value' => 'division_intro'],
                    ['label' => 'wall_math', 'value' => 'wall_math'],
                    ['label' => 'clock', 'value' => 'clock'],
                    ['label' => 'money', 'value' => 'money'],
                    ['label' => 'write_digits', 'value' => 'write_digits'],
                    ['label' => 'write_uppercase', 'value' => 'write_uppercase'],
                    ['label' => 'deutsch_artikel', 'value' => 'deutsch_artikel'],
                    ['label' => 'deutsch_reime', 'value' => 'deutsch_reime'],
                    ['label' => 'deutsch_gross_klein', 'value' => 'deutsch_gross_klein'],
                    ['label' => 'deutsch_wortarten', 'value' => 'deutsch_wortarten'],
                    ['label' => 'deutsch_plural', 'value' => 'deutsch_plural'],
                ],
                'required' => true,
            ],
        ],

        'sort_order' => [
            'label'  => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.topic.sortOrder',
            'config' => [
                'type'    => 'number',
                'format'  => 'integer',
                'default' => 0,
            ],
        ],

        'package' => [
            'label'  => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.topic.package',
            'config' => [
                'type'                => 'select',
                'renderType'          => 'selectSingle',
                'foreign_table'       => 'tx_pipliobackend_package',
                'foreign_table_where' => 'AND tx_pipliobackend_package.pid=###CURRENT_PID### ORDER BY tx_pipliobackend_package.title ASC',
                'required'            => true,
                'size'                => 1,
                'minitems'            => 1,
                'maxitems'            => 1,
            ],
        ],
    ],

    'types' => [
        '0' => [
            'showitem' => 'hidden, topic_id, title, subtitle, color_key, sort_order, package',
        ],
    ],
];
