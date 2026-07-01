<?php
declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'piplio-backend-module' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:piplio_backend/Resources/Public/Icons/module-piplio.svg',
    ],
    'piplio-backend-record' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:piplio_backend/Resources/Public/Icons/Extension.svg',
    ],
];
