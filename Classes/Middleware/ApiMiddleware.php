<?php

declare(strict_types=1);

namespace Aistea\PiplioBackend\Middleware;

use Aistea\PiplioBackend\Utility\WordTopics;
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

class ApiMiddleware implements MiddlewareInterface
{
    private const API_PREFIXES = ['/api/piplio/words', '/api/piplio/v1/words'];
    private const ALLOWED_TOPICS = WordTopics::ALLOWED_TOPICS;
    private const ALLOWED_DIFFICULTIES = WordTopics::ALLOWED_DIFFICULTIES;
    private const ALLOWED_QUERY_PARAMS = ['topic', 'difficulty'];
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

        $params     = $request->getQueryParams();
        if (!$this->hasOnlyAllowedQueryParams($params)) {
            return $this->withCorsHeaders(new JsonResponse([
                'error' => 'Only topic and difficulty query parameters are allowed.',
            ], 400));
        }

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

        if ($topic === '' || $difficulty === '') {
            return $this->withCorsHeaders(new JsonResponse([
                'error' => 'Both topic and difficulty are required.',
                'allowedTopics' => self::ALLOWED_TOPICS,
                'allowedDifficulties' => self::ALLOWED_DIFFICULTIES,
            ], 400));
        }

        $words = $this->fetchWords($topic, $difficulty);

        return $this->withCorsHeaders(new JsonResponse([
            'topic'      => $topic,
            'difficulty' => $difficulty,
            'count'      => count($words),
            'words'      => $words,
        ]));
    }

    private function fetchWords(string $topic, string $difficulty): array
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_pipliobackend_word');

        $qb->select(
            'word', 'topic', 'difficulty', 'artikel', 'word_type',
            'is_nomen', 'plural_form', 'rhyme_words', 'no_rhyme_words', 'wrong_options',
            'correct', 'full_sentence', 'tense_when', 'tense_form', 'syllables', 'punctuation_mark'
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

        $payload = [];
        $skippedRows = 0;
        foreach ($rows as $row) {
            $mappedRow = $this->mapRow($row);
            if ($mappedRow !== null) {
                $payload[] = $mappedRow;
            } else {
                $skippedRows++;
            }
        }

        if ($skippedRows > 0) {
            $this->getLogger()->warning('Skipped invalid Piplio API rows.', [
                'topic' => $topic,
                'difficulty' => $difficulty,
                'skippedRows' => $skippedRows,
            ]);
        }

        return $payload;
    }

    private function mapRow(array $row): ?array
    {
        $word = trim((string)($row['word'] ?? ''));
        $topic = (string)($row['topic'] ?? '');

        if ($word === '' || !in_array($topic, self::ALLOWED_TOPICS, true)) {
            return null;
        }

        return match ($topic) {
            'deutsch_artikel' => $this->mapArtikelRow($word, $row),
            'deutsch_reime' => $this->mapReimeRow($word, $row),
            'deutsch_gross_klein' => $this->mapGrossKleinRow($word, $row),
            'deutsch_wortarten' => $this->mapWortartenRow($word, $row),
            'deutsch_plural' => $this->mapPluralRow($word, $row),
            'deutsch_rechtschreibung' => $this->mapRechtschreibungRow($word, $row),
            'deutsch_zeitformen' => $this->mapZeitformenRow($word, $row),
            'deutsch_silben' => $this->mapSilbenRow($word, $row),
            'deutsch_satzzeichen' => $this->mapSatzzeichenRow($word, $row),
            default => null,
        };
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

    private function hasOnlyAllowedQueryParams(array $params): bool
    {
        foreach (array_keys($params) as $key) {
            if (!in_array((string)$key, self::ALLOWED_QUERY_PARAMS, true)) {
                return false;
            }
        }

        return true;
    }

    private function getLogger(): LoggerInterface
    {
        if ($this->logger === null) {
            $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        }

        return $this->logger;
    }

    private function mapArtikelRow(string $word, array $row): ?array
    {
        $artikel = trim((string)($row['artikel'] ?? ''));
        if (!in_array($artikel, ['der', 'die', 'das'], true)) {
            return null;
        }

        return [
            'word' => $word,
            'artikel' => $artikel,
        ];
    }

    private function mapReimeRow(string $word, array $row): ?array
    {
        $rhymes = $this->splitList((string)($row['rhyme_words'] ?? ''));
        $noRhymes = $this->splitList((string)($row['no_rhyme_words'] ?? ''));
        if ($rhymes === [] || $noRhymes === []) {
            return null;
        }

        return [
            'word' => $word,
            'rhymes' => $rhymes,
            'noRhymes' => $noRhymes,
        ];
    }

    private function mapGrossKleinRow(string $word, array $row): ?array
    {
        $isNomen = (bool)($row['is_nomen'] ?? false);
        $correct = $isNomen ? $this->mbUpperFirst($word) : $this->mbLowerFirst($word);
        $wrong = $isNomen ? $this->mbLowerFirst($word) : $this->mbUpperFirst($word);

        if ($correct === '' || $wrong === '') {
            return null;
        }

        return [
            'correct' => $correct,
            'wrong' => $wrong,
            'isNomen' => $isNomen,
        ];
    }

    private function mapWortartenRow(string $word, array $row): ?array
    {
        $wordType = trim((string)($row['word_type'] ?? ''));
        if (!in_array($wordType, ['Nomen', 'Verb', 'Adjektiv'], true)) {
            return null;
        }

        return [
            'word' => $word,
            'wordType' => $wordType,
        ];
    }

    private function mapPluralRow(string $word, array $row): ?array
    {
        $plural = trim((string)($row['plural_form'] ?? ''));
        $wrong = $this->splitList((string)($row['wrong_options'] ?? ''));
        if ($plural === '' || $wrong === []) {
            return null;
        }

        return [
            'singular' => $word,
            'plural' => $plural,
            'wrong' => $wrong,
        ];
    }

    private function mapRechtschreibungRow(string $word, array $row): ?array
    {
        // The generic "word" column holds the masked Lückentext for this topic.
        $masked = $word;
        if (!str_contains($masked, '__')) {
            return null;
        }

        $correct = trim((string)($row['correct'] ?? ''));
        $wrong = $this->splitList((string)($row['wrong_options'] ?? ''));
        $full = trim((string)($row['full_sentence'] ?? ''));

        if ($correct === '' || $wrong === [] || $full === '') {
            return null;
        }

        return [
            'masked' => $masked,
            'correct' => $correct,
            'wrong' => $wrong,
            'full' => $full,
        ];
    }

    private function mapZeitformenRow(string $word, array $row): ?array
    {
        // The generic "word" column holds the full sentence for this topic.
        $sentence = $word;
        $when = trim((string)($row['tense_when'] ?? ''));
        if (!in_array($when, ['Gegenwart', 'Vergangenheit', 'Zukunft'], true)) {
            return null;
        }

        $form = trim((string)($row['tense_form'] ?? ''));
        if ($form !== '' && !in_array($form, ['Präsens', 'Präteritum', 'Perfekt'], true)) {
            return null;
        }

        $result = [
            'sentence' => $sentence,
            'when' => $when,
        ];
        if ($form !== '') {
            $result['form'] = $form;
        }

        return $result;
    }

    private function mapSilbenRow(string $word, array $row): ?array
    {
        $syllables = (int)($row['syllables'] ?? 0);
        if ($syllables < 1) {
            return null;
        }

        return [
            'word' => $word,
            'syllables' => $syllables,
        ];
    }

    private function mapSatzzeichenRow(string $word, array $row): ?array
    {
        // The generic "word" column holds the sentence text (without end mark) for this topic.
        $mark = trim((string)($row['punctuation_mark'] ?? ''));
        if (!in_array($mark, ['.', '?', '!'], true)) {
            return null;
        }

        return [
            'text' => $word,
            'mark' => $mark,
        ];
    }

    private function mbUpperFirst(string $value): string
    {
        $first = mb_substr($value, 0, 1);
        $rest = mb_substr($value, 1);

        return mb_strtoupper($first) . $rest;
    }

    private function mbLowerFirst(string $value): string
    {
        $first = mb_substr($value, 0, 1);
        $rest = mb_substr($value, 1);

        return mb_strtolower($first) . $rest;
    }
}
