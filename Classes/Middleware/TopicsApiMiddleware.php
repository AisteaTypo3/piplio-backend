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

class TopicsApiMiddleware implements MiddlewareInterface
{
    private const API_PREFIXES = ['/api/piplio/topics', '/api/piplio/v1/topics'];
    private const ALLOWED_GRADES = ['1', '2', '3'];
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
            'packages'             => $this->fetchPackages(),
            'topics'               => $this->fetchTopics(),
            'gradeRecommendations' => $this->fetchGradeRecommendations(),
        ]));
    }

    private function fetchPackages(): array
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_pipliobackend_package');

        $rows = $qb->select('package_id', 'title', 'description', 'recommended_grade')
            ->from('tx_pipliobackend_package')
            ->where($qb->expr()->eq('hidden', $qb->createNamedParameter(0, Connection::PARAM_INT)))
            ->andWhere($qb->expr()->eq('deleted', $qb->createNamedParameter(0, Connection::PARAM_INT)))
            ->orderBy('title')
            ->executeQuery()
            ->fetchAllAssociative();

        $payload = [];
        foreach ($rows as $row) {
            $mapped = $this->mapPackageRow($row);
            if ($mapped !== null) {
                $payload[] = $mapped;
            }
        }

        return $payload;
    }

    private function fetchTopics(): array
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_pipliobackend_topic');

        $rows = $qb->select('t.topic_id', 't.title', 't.subtitle', 't.color_key', 't.sort_order', 'p.package_id')
            ->from('tx_pipliobackend_topic', 't')
            ->join(
                't',
                'tx_pipliobackend_package',
                'p',
                (string)$qb->expr()->eq('t.package', $qb->quoteIdentifier('p.uid'))
            )
            ->where($qb->expr()->eq('t.hidden', $qb->createNamedParameter(0, Connection::PARAM_INT)))
            ->andWhere($qb->expr()->eq('t.deleted', $qb->createNamedParameter(0, Connection::PARAM_INT)))
            ->andWhere($qb->expr()->eq('p.hidden', $qb->createNamedParameter(0, Connection::PARAM_INT)))
            ->andWhere($qb->expr()->eq('p.deleted', $qb->createNamedParameter(0, Connection::PARAM_INT)))
            ->orderBy('t.sort_order')
            ->executeQuery()
            ->fetchAllAssociative();

        $payload = [];
        $skippedRows = 0;
        foreach ($rows as $row) {
            $mapped = $this->mapTopicRow($row);
            if ($mapped !== null) {
                $payload[] = $mapped;
            } else {
                $skippedRows++;
            }
        }

        if ($skippedRows > 0) {
            $this->getLogger()->warning('Skipped invalid Piplio topic rows.', ['skippedRows' => $skippedRows]);
        }

        return $payload;
    }

    private function fetchGradeRecommendations(): array
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_pipliobackend_graderecommendation');

        $rows = $qb->select('g.grade', 'p.package_id')
            ->from('tx_pipliobackend_graderecommendation', 'g')
            ->join(
                'g',
                'tx_pipliobackend_package',
                'p',
                (string)$qb->expr()->eq('g.package', $qb->quoteIdentifier('p.uid'))
            )
            ->where($qb->expr()->eq('g.hidden', $qb->createNamedParameter(0, Connection::PARAM_INT)))
            ->andWhere($qb->expr()->eq('g.deleted', $qb->createNamedParameter(0, Connection::PARAM_INT)))
            ->andWhere($qb->expr()->eq('p.hidden', $qb->createNamedParameter(0, Connection::PARAM_INT)))
            ->andWhere($qb->expr()->eq('p.deleted', $qb->createNamedParameter(0, Connection::PARAM_INT)))
            ->orderBy('g.grade')
            ->addOrderBy('g.sorting')
            ->executeQuery()
            ->fetchAllAssociative();

        $result = [];
        foreach (self::ALLOWED_GRADES as $grade) {
            $result[$grade] = [];
        }

        foreach ($rows as $row) {
            $grade = (string)($row['grade'] ?? '');
            $packageId = trim((string)($row['package_id'] ?? ''));
            if (!in_array($grade, self::ALLOWED_GRADES, true) || $packageId === '') {
                continue;
            }
            $result[$grade][] = $packageId;
        }

        return $result;
    }

    private function mapPackageRow(array $row): ?array
    {
        $id = trim((string)($row['package_id'] ?? ''));
        $title = trim((string)($row['title'] ?? ''));
        $recommendedGrade = (string)($row['recommended_grade'] ?? '');

        if ($id === '' || $title === '' || !in_array($recommendedGrade, self::ALLOWED_GRADES, true)) {
            return null;
        }

        return [
            'id'               => $id,
            'title'            => $title,
            'description'      => (string)($row['description'] ?? ''),
            'recommendedGrade' => $recommendedGrade,
        ];
    }

    private function mapTopicRow(array $row): ?array
    {
        $id = trim((string)($row['topic_id'] ?? ''));
        $title = trim((string)($row['title'] ?? ''));
        $packageId = trim((string)($row['package_id'] ?? ''));

        if ($id === '' || $title === '' || $packageId === '') {
            return null;
        }

        return [
            'id'        => $id,
            'title'     => $title,
            'subtitle'  => (string)($row['subtitle'] ?? ''),
            'colorKey'  => (string)($row['color_key'] ?? ''),
            'order'     => (int)($row['sort_order'] ?? 0),
            'packageId' => $packageId,
        ];
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
