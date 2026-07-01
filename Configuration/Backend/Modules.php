<?php
declare(strict_types=1);

use Aistea\PiplioBackend\Controller\Backend\WordModuleController;

return [
    'piplio_backend' => [
        'parent' => 'web',
        'position' => ['after' => 'web_info'],
        'access' => 'user',
        'path' => '/module/piplio/backend',
        'iconIdentifier' => 'piplio-backend-module',
        'labels' => [
            'title' => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang_module.xlf:module.title',
            'description' => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang_module.xlf:module.description',
            'shortDescription' => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang_module.xlf:module.shortDescription',
        ],
        'extensionName' => 'PiplioBackend',
        'controllerActions' => [
            WordModuleController::class => ['index'],
        ],
    ],
];
