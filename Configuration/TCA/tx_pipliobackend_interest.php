<?php

return [
    'ctrl' => [
        'title' => 'Piplio Interesse',
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
            'label' => 'Versteckt',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
            ],
        ],
        'name' => [
            'label' => 'Name',
            'config' => [
                'type' => 'input',
                'size' => 40,
                'required' => true,
                'eval' => 'trim',
            ],
        ],
        'email' => [
            'label' => 'E-Mail',
            'config' => [
                'type' => 'input',
                'size' => 40,
                'required' => true,
                'eval' => 'trim,email',
            ],
        ],
        'page_title' => [
            'label' => 'Seitentitel',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'eval' => 'trim',
            ],
        ],
        'page_url' => [
            'label' => 'Seiten-URL',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'eval' => 'trim',
            ],
        ],
        'source_page_id' => [
            'label' => 'Quellseiten-PID',
            'config' => [
                'type' => 'number',
                'format' => 'integer',
                'default' => 0,
            ],
        ],
        'remote_address' => [
            'label' => 'IP-Adresse',
            'config' => [
                'type' => 'input',
                'readOnly' => true,
                'size' => 20,
            ],
        ],
        'user_agent' => [
            'label' => 'User Agent',
            'config' => [
                'type' => 'text',
                'readOnly' => true,
                'rows' => 3,
                'cols' => 40,
            ],
        ],
        'consent_timestamp' => [
            'label' => 'Consent-Zeitpunkt',
            'config' => [
                'type' => 'datetime',
                'format' => 'datetime',
                'readOnly' => true,
                'default' => 0,
            ],
        ],
        'privacy_version' => [
            'label' => 'Datenschutz-Version',
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
                --div--;Kontakt, name, email,
                --div--;Quelle, page_title, page_url, source_page_id,
                --div--;Datenschutz, consent_timestamp, privacy_version,
                --div--;Technik, remote_address, user_agent
            ',
        ],
    ],
];
