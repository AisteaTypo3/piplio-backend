<?php
return [
    'ctrl' => [
        'title'            => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.badge.title',
        'label'            => 'title',
        'label_alt'        => 'badge_id,category',
        'label_alt_force'  => true,
        'tstamp'           => 'tstamp',
        'crdate'           => 'crdate',
        'delete'           => 'deleted',
        'enablecolumns'    => ['disabled' => 'hidden'],
        'security'         => [
            'ignorePageTypeRestriction' => true,
        ],
        'searchFields'     => 'badge_id,title',
        'iconfile'         => 'EXT:piplio_backend/Resources/Public/Icons/Extension.svg',
        'default_sortby'   => 'category ASC, title ASC',
    ],

    'columns' => [
        'hidden' => [
            'label'  => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.badge.hidden',
            'config' => ['type' => 'check', 'renderType' => 'checkboxToggle'],
        ],

        'badge_id' => [
            'label'  => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.badge.badgeId',
            'config' => [
                'type'     => 'input',
                'size'     => 30,
                'required' => true,
                'eval'     => 'trim,unique',
            ],
        ],

        'category' => [
            'label'  => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.badge.category',
            'config' => [
                'type'       => 'select',
                'renderType' => 'selectSingle',
                'items'      => [
                    ['label' => 'milestone', 'value' => 'milestone'],
                    ['label' => 'streak', 'value' => 'streak'],
                ],
                'default'  => 'milestone',
                'required' => true,
            ],
        ],

        'title' => [
            'label'  => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.badge.badgeTitle',
            'config' => [
                'type'     => 'input',
                'size'     => 40,
                'required' => true,
                'eval'     => 'trim',
            ],
        ],

        'description' => [
            'label'  => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.badge.description',
            'config' => [
                'type' => 'text',
                'rows' => 3,
                'cols' => 40,
                'eval' => 'trim',
            ],
        ],

        'icon' => [
            'label'  => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.badge.icon',
            'config' => [
                'type'       => 'select',
                'renderType' => 'selectSingle',
                'items'      => [
                    ['label' => 'rocket', 'value' => 'rocket'],
                    ['label' => 'star', 'value' => 'star'],
                    ['label' => 'star2', 'value' => 'star2'],
                    ['label' => 'trophy', 'value' => 'trophy'],
                    ['label' => 'flame', 'value' => 'flame'],
                    ['label' => 'flame2', 'value' => 'flame2'],
                    ['label' => 'ice_cream', 'value' => 'ice_cream'],
                    ['label' => 'movie', 'value' => 'movie'],
                    ['label' => 'moon', 'value' => 'moon'],
                    ['label' => 'game', 'value' => 'game'],
                    ['label' => 'crown', 'value' => 'crown'],
                    ['label' => 'shield', 'value' => 'shield'],
                    ['label' => 'lightning', 'value' => 'lightning'],
                    ['label' => 'diamond', 'value' => 'diamond'],
                    ['label' => 'book', 'value' => 'book'],
                ],
                'required' => true,
            ],
        ],

        'xp_required' => [
            'label'  => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.badge.xpRequired',
            'config' => [
                'type'     => 'number',
                'format'   => 'integer',
                'nullable' => true,
            ],
        ],

        'streak_required' => [
            'label'  => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.badge.streakRequired',
            'config' => [
                'type'     => 'number',
                'format'   => 'integer',
                'nullable' => true,
            ],
        ],

        'total_sessions_required' => [
            'label'  => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.badge.totalSessionsRequired',
            'config' => [
                'type'     => 'number',
                'format'   => 'integer',
                'nullable' => true,
            ],
        ],
    ],

    'types' => [
        '0' => [
            'showitem' => 'hidden, badge_id, category, title, description, icon, xp_required, streak_required, total_sessions_required',
        ],
    ],
];
