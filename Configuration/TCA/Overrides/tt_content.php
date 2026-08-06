<?php

declare(strict_types=1);

defined('TYPO3') or die();

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

ExtensionUtility::registerPlugin(
    'PiplioBackend',
    'InterestWidget',
    'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:plugin.interestWidget.title',
    'piplio-backend-record'
);

$pluginSignature = 'pipliobackend_interestwidget';
ExtensionManagementUtility::addPiFlexFormValue(
    '*',
    'FILE:EXT:piplio_backend/Configuration/FlexForms/InterestWidget.xml',
    $pluginSignature
);
ExtensionManagementUtility::addToAllTCAtypes('tt_content', 'pi_flexform', $pluginSignature, 'after:subheader');
