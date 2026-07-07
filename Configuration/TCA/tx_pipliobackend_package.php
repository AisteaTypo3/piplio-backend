<?php
return [
    'ctrl' => [
        'title'            => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.package.title',
        'label'            => 'title',
        'label_alt'        => 'package_id',
        'label_alt_force'  => true,
        'tstamp'           => 'tstamp',
        'crdate'           => 'crdate',
        'delete'           => 'deleted',
        'enablecolumns'    => ['disabled' => 'hidden'],
        'security'         => [
            'ignorePageTypeRestriction' => true,
        ],
        'searchFields'     => 'package_id,title',
        'iconfile'         => 'EXT:piplio_backend/Resources/Public/Icons/Extension.svg',
        'default_sortby'   => 'title ASC',
    ],

    'columns' => [
        'hidden' => [
            'label'  => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.package.hidden',
            'config' => ['type' => 'check', 'renderType' => 'checkboxToggle'],
        ],

        'package_id' => [
            'label'  => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.package.packageId',
            'config' => [
                'type'     => 'input',
                'size'     => 30,
                'required' => true,
                'eval'     => 'trim,unique',
            ],
        ],

        'title' => [
            'label'  => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.package.packageTitle',
            'config' => [
                'type'     => 'input',
                'size'     => 40,
                'required' => true,
                'eval'     => 'trim',
            ],
        ],

        'description' => [
            'label'  => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.package.description',
            'config' => [
                'type' => 'text',
                'rows' => 3,
                'cols' => 40,
                'eval' => 'trim',
            ],
        ],

        'recommended_grade' => [
            'label'  => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.package.recommendedGrade',
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

        'enabled_grades' => [
            'label'  => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.package.enabledGrades',
            'config' => [
                'type'       => 'select',
                'renderType' => 'selectCheckBox',
                'items'      => [
                    ['label' => '1', 'value' => '1'],
                    ['label' => '2', 'value' => '2'],
                    ['label' => '3', 'value' => '3'],
                ],
                'default'  => '1',
                'minitems' => 1,
            ],
        ],
    ],

    'types' => [
        '0' => [
            'showitem' => 'hidden, package_id, title, description, recommended_grade, enabled_grades',
        ],
    ],
];
