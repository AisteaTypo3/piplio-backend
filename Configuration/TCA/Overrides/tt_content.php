<?php

declare(strict_types=1);

defined('TYPO3') or die();

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

ExtensionUtility::registerPlugin(
    'PiplioBackend',
    'InterestWidget',
    'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:plugin.interestWidget.title',
    'piplio-backend-record'
);
