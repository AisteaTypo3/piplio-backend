<?php

return [
    'ctrl' => [
        'title' => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.interest.title',
        'label' => 'email',
        'label_alt' => 'name,page_title',
        'label_alt_force' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'enablecolumns' => ['disabled' => 'hidden'],
        'searchFields' => 'name,email,page_title,page_url',
        'iconfile' => 'EXT:piplio_backend/Resources/Public/Icons/Extension.svg',
        'default_sortby' => 'crdate DESC',
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
    ],
    'columns' => [
        'hidden' => [
            'label' => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.interest.hidden',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
            ],
        ],
        'name' => [
            'label' => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.interest.name',
            'config' => [
                'type' => 'input',
                'size' => 40,
                'required' => true,
                'eval' => 'trim',
            ],
        ],
        'email' => [
            'label' => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.interest.email',
            'config' => [
                'type' => 'input',
                'size' => 40,
                'required' => true,
                'eval' => 'trim,email',
            ],
        ],
        'page_title' => [
            'label' => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.interest.pageTitle',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'eval' => 'trim',
            ],
        ],
        'page_url' => [
            'label' => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.interest.pageUrl',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'eval' => 'trim',
            ],
        ],
        'source_page_id' => [
            'label' => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.interest.sourcePageId',
            'config' => [
                'type' => 'number',
                'format' => 'integer',
                'default' => 0,
            ],
        ],
        'remote_address' => [
            'label' => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.interest.remoteAddress',
            'config' => [
                'type' => 'input',
                'readOnly' => true,
                'size' => 20,
            ],
        ],
        'user_agent' => [
            'label' => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.interest.userAgent',
            'config' => [
                'type' => 'text',
                'readOnly' => true,
                'rows' => 3,
                'cols' => 40,
            ],
        ],
        'consent_timestamp' => [
            'label' => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.interest.consentTimestamp',
            'config' => [
                'type' => 'datetime',
                'format' => 'datetime',
                'readOnly' => true,
                'default' => 0,
            ],
        ],
        'privacy_version' => [
            'label' => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.interest.privacyVersion',
            'config' => [
                'type' => 'input',
                'readOnly' => true,
                'size' => 20,
            ],
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => '
                hidden,
                --div--;LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.tab.contact, name, email,
                --div--;LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.tab.source, page_title, page_url, source_page_id,
                --div--;LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.tab.privacy, consent_timestamp, privacy_version,
                --div--;LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.tab.technical, remote_address, user_agent
            ',
        ],
    ],
];
