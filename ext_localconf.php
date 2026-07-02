<?php

declare(strict_types=1);

defined('TYPO3') or die();

use Aistea\PiplioBackend\Controller\Frontend\InterestWidgetController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

ExtensionUtility::configurePlugin(
    'PiplioBackend',
    'InterestWidget',
    [
        InterestWidgetController::class => 'show,submit',
    ],
    [
        InterestWidgetController::class => 'submit',
    ]
);
