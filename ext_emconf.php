<?php
$EM_CONF[$_EXTKEY] = [
    'title' => 'Piplio Backend',
    'description' => 'Word list management and API for the Piplio / MathQuest learning app',
    'category' => 'plugin',
    'author' => 'Yannick Aister',
    'author_email' => 'info.aister@gmail.com',
    'state' => 'stable',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => ['typo3' => '14.0.0-14.99.99'],
        'conflicts' => [],
        'suggests' => [],
    ],
    'autoload' => [
        'psr-4' => [
            'Aistea\\PiplioBackend\\' => 'Classes/',
        ],
    ],
];
