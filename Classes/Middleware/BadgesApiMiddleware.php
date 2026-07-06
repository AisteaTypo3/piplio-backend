<?php

declare(strict_types=1);

namespace Aistea\PiplioBackend\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class BadgesApiMiddleware implements MiddlewareInterface
{
    private const API_PREFIXES = ['/api/piplio/badges', '/api/piplio/v1/badges'];
    private const ALLOWED_CATEGORIES = ['milestone', 'streak'];
    private ?LoggerInterface $logger = null;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        if (!$this->matchesApiPath($path)) {
            return $handler->handle($request);
        }

        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return $this->withCorsHeaders(new JsonResponse([
                'ok' => true,
            ]));
        }

        $configuredApiKey = $this->getConfiguredApiKey();
        if ($configuredApiKey === '') {
            return $this->withCorsHeaders(new JsonResponse([
                'error' => 'API key is not configured.',
            ], 503));
        }

        if (!$this->isAuthorized($request, $configuredApiKey)) {
            return $this->withCorsHeaders(new JsonResponse([
                'error' => 'Unauthorized.',
            ], 401));
        }

        return $this->withCorsHeaders(new JsonResponse([
            'badges' => $this->fetchBadges(),
        ]));
    }

    private function fetchBadges(): array
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_pipliobackend_badge');

        $rows = $qb->select(
            'badge_id', 'category', 'title', 'description', 'icon',
            'xp_required', 'streak_required', 'total_sessions_required'
        )
            ->from('tx_pipliobackend_badge')
            ->where($qb->expr()->eq('hidden', $qb->createNamedParameter(0, Connection::PARAM_INT)))
            ->andWhere($qb->expr()->eq('deleted', $qb->createNamedParameter(0, Connection::PARAM_INT)))
            ->orderBy('category')
            ->addOrderBy('title')
            ->executeQuery()
            ->fetchAllAssociative();

        $payload = [];
        $skippedRows = 0;
        foreach ($rows as $row) {
            $mapped = $this->mapBadgeRow($row);
            if ($mapped !== null) {
                $payload[] = $mapped;
            } else {
                $skippedRows++;
            }
        }

        if ($skippedRows > 0) {
            $this->getLogger()->warning('Skipped invalid Piplio badge rows.', ['skippedRows' => $skippedRows]);
        }

        return $payload;
    }

    private function mapBadgeRow(array $row): ?array
    {
        $id = trim((string)($row['badge_id'] ?? ''));
        $title = trim((string)($row['title'] ?? ''));
        $icon = trim((string)($row['icon'] ?? ''));
        $category = trim((string)($row['category'] ?? ''));

        if ($id === '' || $title === '' || $icon === '' || !in_array($category, self::ALLOWED_CATEGORIES, true)) {
            return null;
        }

        $badge = [
            'id'          => $id,
            'category'    => $category,
            'title'       => $title,
            'description' => (string)($row['description'] ?? ''),
            'icon'        => $icon,
        ];

        if ($row['xp_required'] !== null) {
            $badge['xpRequired'] = (int)$row['xp_required'];
        }
        if ($row['streak_required'] !== null) {
            $badge['streakRequired'] = (int)$row['streak_required'];
        }
        if ($row['total_sessions_required'] !== null) {
            $badge['totalSessionsRequired'] = (int)$row['total_sessions_required'];
        }

        return $badge;
    }

    private function withCorsHeaders(JsonResponse $response): JsonResponse
    {
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Piplio-Api-Key')
            ->withHeader('Access-Control-Max-Age', '86400');
    }

    private function matchesApiPath(string $path): bool
    {
        foreach (self::API_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function getConfiguredApiKey(): string
    {
        try {
            $settings = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('piplio_backend');
        } catch (\Throwable) {
            return '';
        }

        if (!is_array($settings)) {
            return '';
        }

        return trim((string)($settings['apiKey'] ?? ''));
    }

    private function isAuthorized(ServerRequestInterface $request, string $configuredApiKey): bool
    {
        $bearerToken = $this->extractBearerToken($request->getHeaderLine('Authorization'));
        $headerToken = trim($request->getHeaderLine('X-Piplio-Api-Key'));

        if ($bearerToken !== '' && hash_equals($configuredApiKey, $bearerToken)) {
            return true;
        }

        if ($headerToken !== '' && hash_equals($configuredApiKey, $headerToken)) {
            return true;
        }

        return false;
    }

    private function extractBearerToken(string $authorizationHeader): string
    {
        if (preg_match('/^\s*Bearer\s+(.+)\s*$/i', $authorizationHeader, $matches) !== 1) {
            return '';
        }

        return trim((string)($matches[1] ?? ''));
    }

    private function getLogger(): LoggerInterface
    {
        if ($this->logger === null) {
            $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        }

        return $this->logger;
    }
}
