<?php

declare(strict_types=1);

namespace Aistea\PiplioBackend\Service;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

final class InterestLeadService
{
    private const MIN_FORM_AGE_SECONDS = 2;
    private const MAX_SUBMISSIONS_PER_IP_PER_HOUR = 5;
    private const MAX_SUBMISSIONS_PER_EMAIL_PER_DAY = 2;
    private const DEV_MAX_SUBMISSIONS_PER_IP_PER_HOUR = 100;
    private const DEV_MAX_SUBMISSIONS_PER_EMAIL_PER_DAY = 25;

    public function processSubmission(array $input, string $remoteAddress, string $userAgent, int $fallbackPageId = 0): array
    {
        $name = trim((string)($input['name'] ?? ''));
        $email = trim((string)($input['email'] ?? ''));
        $website = trim((string)($input['website'] ?? ''));
        $pageTitle = $this->limitString((string)($input['pageTitle'] ?? ''), 255);
        $pageUrl = $this->limitString((string)($input['pageUrl'] ?? ''), 2048);
        $pageId = max(0, (int)($input['pageId'] ?? $fallbackPageId));
        $privacyVersion = $this->normalizePrivacyVersion((string)($input['privacyVersion'] ?? ''));
        $privacyAccepted = filter_var($input['privacyAccepted'] ?? false, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        $privacyAccepted = $privacyAccepted ?? in_array((string)($input['privacyAccepted'] ?? ''), ['1', 'on', 'yes', 'true'], true);
        $formTimestamp = (int)($input['formTimestamp'] ?? 0);

        if ($website !== '' || ($formTimestamp > 0 && (time() - $formTimestamp) < self::MIN_FORM_AGE_SECONDS)) {
            return [
                'ok' => true,
                'status' => 200,
                'message' => $this->translate('message.accepted'),
            ];
        }

        if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => $this->translate('message.invalidName'),
            ];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => $this->translate('message.invalidEmail'),
            ];
        }

        if (!$privacyAccepted) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => $this->translate('message.privacyRequired'),
            ];
        }

        $rateLimitError = $this->checkRateLimit($remoteAddress, $email);
        if ($rateLimitError !== null) {
            return [
                'ok' => false,
                'status' => 429,
                'message' => $rateLimitError,
            ];
        }

        $this->storeInterestLead($name, $email, $pageTitle, $pageUrl, $pageId, $remoteAddress, $userAgent, $privacyVersion);

        return [
            'ok' => true,
            'status' => 200,
            'message' => $this->translate('message.success'),
        ];
    }

    public function resolvePrivacyVersion(): string
    {
        try {
            $settings = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('piplio_backend');
        } catch (\Throwable) {
            $settings = [];
        }

        return $this->normalizePrivacyVersion((string)(is_array($settings) ? ($settings['privacyVersion'] ?? '') : ''));
    }

    public function resolveStoragePid(int $pageId): int
    {
        try {
            $settings = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('piplio_backend');
        } catch (\Throwable) {
            $settings = [];
        }

        $configuredPid = is_array($settings) ? (int)($settings['interestStoragePid'] ?? 0) : 0;
        if ($configuredPid > 0) {
            return $configuredPid;
        }

        return max(1, $pageId);
    }

    private function storeInterestLead(
        string $name,
        string $email,
        string $pageTitle,
        string $pageUrl,
        int $pageId,
        string $remoteAddress,
        string $userAgent,
        string $privacyVersion
    ): void {
        $storagePid = $this->resolveStoragePid($pageId);
        $timestamp = time();

        GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_pipliobackend_interest')
            ->insert(
                'tx_pipliobackend_interest',
                [
                    'pid' => $storagePid,
                    'tstamp' => $timestamp,
                    'crdate' => $timestamp,
                    'hidden' => 0,
                    'deleted' => 0,
                    'name' => $name,
                    'email' => $email,
                    'page_title' => $pageTitle,
                    'page_url' => $pageUrl,
                    'source_page_id' => $pageId,
                    'remote_address' => $this->limitString($remoteAddress, 64),
                    'user_agent' => $this->limitString($userAgent, 512),
                    'consent_timestamp' => $timestamp,
                    'privacy_version' => $privacyVersion,
                ],
                [
                    'pid' => Connection::PARAM_INT,
                    'tstamp' => Connection::PARAM_INT,
                    'crdate' => Connection::PARAM_INT,
                    'hidden' => Connection::PARAM_INT,
                    'deleted' => Connection::PARAM_INT,
                    'source_page_id' => Connection::PARAM_INT,
                    'consent_timestamp' => Connection::PARAM_INT,
                ]
            );
    }

    private function checkRateLimit(string $remoteAddress, string $email): ?string
    {
        $oneHourAgo = time() - 3600;
        $oneDayAgo = time() - 86400;
        $ipLimit = $this->isDevelopmentContext()
            ? self::DEV_MAX_SUBMISSIONS_PER_IP_PER_HOUR
            : self::MAX_SUBMISSIONS_PER_IP_PER_HOUR;
        $emailLimit = $this->isDevelopmentContext()
            ? self::DEV_MAX_SUBMISSIONS_PER_EMAIL_PER_DAY
            : self::MAX_SUBMISSIONS_PER_EMAIL_PER_DAY;

        if ($remoteAddress !== '') {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable('tx_pipliobackend_interest');

            $recentIpCount = (int)$queryBuilder
                ->count('*')
                ->from('tx_pipliobackend_interest')
                ->where(
                    $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                    $queryBuilder->expr()->eq('remote_address', $queryBuilder->createNamedParameter($remoteAddress)),
                    $queryBuilder->expr()->gte('crdate', $queryBuilder->createNamedParameter($oneHourAgo, Connection::PARAM_INT))
                )
                ->executeQuery()
                ->fetchOne();

            if ($recentIpCount >= $ipLimit) {
                return $this->translate('message.tooManyRequests');
            }
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_pipliobackend_interest');

        $recentEmailCount = (int)$queryBuilder
            ->count('*')
            ->from('tx_pipliobackend_interest')
            ->where(
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('email', $queryBuilder->createNamedParameter($email)),
                $queryBuilder->expr()->gte('crdate', $queryBuilder->createNamedParameter($oneDayAgo, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchOne();

        if ($recentEmailCount >= $emailLimit) {
            return $this->translate('message.duplicateEmail');
        }

        return null;
    }

    private function isDevelopmentContext(): bool
    {
        return Environment::getContext()->isDevelopment();
    }

    private function normalizePrivacyVersion(string $privacyVersion): string
    {
        $privacyVersion = trim($privacyVersion);
        if ($privacyVersion === '') {
            return 'v1';
        }

        return mb_substr($privacyVersion, 0, 32);
    }

    private function limitString(string $value, int $maxLength): string
    {
        return mb_substr(trim($value), 0, $maxLength);
    }

    private function translate(string $key): string
    {
        return (string)(LocalizationUtility::translate($key, 'PiplioBackend') ?? $key);
    }
}
