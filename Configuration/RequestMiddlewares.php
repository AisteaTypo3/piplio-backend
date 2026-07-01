<?php
return [
    'frontend' => [
        'aistea/piplio-backend/api' => [
            'target' => \Aistea\PiplioBackend\Middleware\ApiMiddleware::class,
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
            'after' => [
                'typo3/cms-core/normalized-params-attribute',
            ],
        ],
    ],
];
