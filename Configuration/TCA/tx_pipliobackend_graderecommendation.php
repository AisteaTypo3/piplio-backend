<?php
return [
    'ctrl' => [
        'title'            => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.gradeRecommendation.title',
        'label'            => 'grade',
        'label_alt'        => 'package',
        'label_alt_force'  => true,
        'tstamp'           => 'tstamp',
        'crdate'           => 'crdate',
        'delete'           => 'deleted',
        'enablecolumns'    => ['disabled' => 'hidden'],
        'security'         => [
            'ignorePageTypeRestriction' => true,
        ],
        'sortby'           => 'sorting',
        'iconfile'         => 'EXT:piplio_backend/Resources/Public/Icons/Extension.svg',
    ],

    'columns' => [
        'hidden' => [
            'label'  => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.gradeRecommendation.hidden',
            'config' => ['type' => 'check', 'renderType' => 'checkboxToggle'],
        ],

        'grade' => [
            'label'  => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.gradeRecommendation.grade',
            'config' => [
                'type'       => 'select',
                'renderType' => 'selectSingle',
                'items'      => [
                    ['label' => '1', 'value' => '1'],
                    ['label' => '2', 'value' => '2'],
                    ['label' => '3', 'value' => '3'],
                ],
                'default'  => '1',
                'required' => true,
            ],
        ],

        'package' => [
            'label'  => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.gradeRecommendation.package',
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
            'showitem' => 'hidden, grade, package',
        ],
    ],
];
