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
        'aistea/piplio-backend/interest-submit' => [
            'target' => \Aistea\PiplioBackend\Middleware\InterestSubmitMiddleware::class,
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
            'after' => [
                'typo3/cms-core/normalized-params-attribute',
            ],
        ],
        'aistea/piplio-backend/topics-api' => [
            'target' => \Aistea\PiplioBackend\Middleware\TopicsApiMiddleware::class,
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
            'after' => [
                'typo3/cms-core/normalized-params-attribute',
            ],
        ],
        'aistea/piplio-backend/badges-api' => [
            'target' => \Aistea\PiplioBackend\Middleware\BadgesApiMiddleware::class,
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
            'after' => [
                'typo3/cms-core/normalized-params-attribute',
            ],
        ],
    ],
];
