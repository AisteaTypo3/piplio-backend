<?php

declare(strict_types=1);

namespace Aistea\PiplioBackend\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ApiMiddleware implements MiddlewareInterface
{
    private const API_PREFIX = '/api/piplio/words';
    private const ALLOWED_TOPICS = [
        'deutsch_artikel',
        'deutsch_reime',
        'deutsch_gross_klein',
        'deutsch_wortarten',
        'deutsch_plural',
    ];
    private const ALLOWED_DIFFICULTIES = ['easy', 'medium', 'hard'];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        if (!str_starts_with($path, self::API_PREFIX)) {
            return $handler->handle($request);
        }

        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return $this->withCorsHeaders(new JsonResponse([
                'ok' => true,
            ]));
        }

        $params     = $request->getQueryParams();
        $topic      = $this->normalizeTopic((string)($params['topic'] ?? ''));
        $difficulty = $this->normalizeDifficulty((string)($params['difficulty'] ?? ''));

        if ($topic === null) {
            return $this->withCorsHeaders(new JsonResponse([
                'error' => 'Invalid topic parameter.',
                'allowedTopics' => self::ALLOWED_TOPICS,
            ], 400));
        }

        if ($difficulty === null) {
            return $this->withCorsHeaders(new JsonResponse([
                'error' => 'Invalid difficulty parameter.',
                'allowedDifficulties' => self::ALLOWED_DIFFICULTIES,
            ], 400));
        }

        $words = $this->fetchWords($topic, $difficulty);

        return $this->withCorsHeaders(new JsonResponse([
            'topic'      => $topic,
            'difficulty' => $difficulty,
            'count'      => count($words),
            'filtersApplied' => [
                'topic' => $topic !== '',
                'difficulty' => $difficulty !== '',
            ],
            'words'      => $words,
        ]));
    }

    private function fetchWords(string $topic, string $difficulty): array
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_pipliobackend_word');

        $qb->select(
            'word', 'topic', 'difficulty', 'artikel', 'word_type',
            'is_nomen', 'plural_form', 'rhyme_words', 'no_rhyme_words', 'wrong_options'
        )
            ->from('tx_pipliobackend_word')
            ->where($qb->expr()->eq('hidden', $qb->createNamedParameter(0, Connection::PARAM_INT)))
            ->andWhere($qb->expr()->eq('deleted', $qb->createNamedParameter(0, Connection::PARAM_INT)))
            ->orderBy('topic')
            ->addOrderBy('difficulty')
            ->addOrderBy('word');

        if ($topic !== '') {
            $qb->andWhere($qb->expr()->eq('topic', $qb->createNamedParameter($topic)));
        }
        if ($difficulty !== '') {
            $qb->andWhere($qb->expr()->eq('difficulty', $qb->createNamedParameter($difficulty)));
        }

        $rows = $qb->executeQuery()->fetchAllAssociative();

        $includeMetaFields = !($topic !== '' && $difficulty !== '');

        return array_map(
            fn(array $row): array => $this->mapRow($row, $includeMetaFields),
            $rows
        );
    }

    private function mapRow(array $row, bool $includeMetaFields): array
    {
        $word  = $row['word'];
        $topic = $row['topic'];
        $difficulty = (string)($row['difficulty'] ?? '');

        $payload = match ($topic) {
            'deutsch_artikel' => [
                'word'    => $word,
                'artikel' => $row['artikel'],
            ],
            'deutsch_reime' => [
                'word'      => $word,
                'rhymes'    => $this->splitList($row['rhyme_words']),
                'noRhymes'  => $this->splitList($row['no_rhyme_words']),
            ],
            'deutsch_gross_klein' => [
                'correct' => (bool)$row['is_nomen'] ? ucfirst($word) : lcfirst($word),
                'wrong'   => (bool)$row['is_nomen'] ? lcfirst($word) : ucfirst($word),
                'isNomen' => (bool)$row['is_nomen'],
            ],
            'deutsch_wortarten' => [
                'word'     => $word,
                'wordType' => $row['word_type'],
            ],
            'deutsch_plural' => [
                'singular' => $word,
                'plural'   => $row['plural_form'],
                'wrong'    => $this->splitList($row['wrong_options']),
            ],
            default => ['word' => $word],
        };

        if (!$includeMetaFields) {
            return $payload;
        }

        return array_merge([
            'topic' => $topic,
            'difficulty' => $difficulty,
        ], $payload);
    }

    private function splitList(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    private function normalizeTopic(string $topic): ?string
    {
        $topic = trim($topic);
        if ($topic === '') {
            return '';
        }

        return in_array($topic, self::ALLOWED_TOPICS, true) ? $topic : null;
    }

    private function normalizeDifficulty(string $difficulty): ?string
    {
        $difficulty = trim($difficulty);
        if ($difficulty === '') {
            return '';
        }

        return in_array($difficulty, self::ALLOWED_DIFFICULTIES, true) ? $difficulty : null;
    }

    private function withCorsHeaders(JsonResponse $response): JsonResponse
    {
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->withHeader('Access-Control-Max-Age', '86400');
    }
}
