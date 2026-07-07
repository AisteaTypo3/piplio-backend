<?php

declare(strict_types=1);

namespace Aistea\PiplioBackend\Service;

use Aistea\PiplioBackend\Utility\WordTopics;
use PhpOffice\PhpSpreadsheet\IOFactory;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Parses an uploaded spreadsheet (one row = one tx_pipliobackend_word record,
 * generic columns shared across all topics) and applies it to the database
 * in one of three modes: append, upsert ("überschreiben"), or replace ("ersetzen").
 */
final class WordImportService
{
    public const MODE_APPEND = 'append';
    public const MODE_UPSERT = 'upsert';
    public const MODE_REPLACE = 'replace';
    public const MODES = [self::MODE_APPEND, self::MODE_UPSERT, self::MODE_REPLACE];

    private const REQUIRED_COLUMNS = ['topic', 'difficulty', 'word'];

    /**
     * @return array{rows: list<array<string, string>>, errors: list<array{row: int, message: string}>}
     */
    public function parseFile(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray(null, true, true, false);

        if ($data === []) {
            return ['rows' => [], 'errors' => [['row' => 0, 'message' => 'The file contains no data.']]];
        }

        $header = array_map(
            static fn($value): string => strtolower(trim((string)$value)),
            array_shift($data)
        );

        $missingColumns = array_diff(self::REQUIRED_COLUMNS, $header);
        if ($missingColumns !== []) {
            return ['rows' => [], 'errors' => [[
                'row' => 1,
                'message' => 'Missing required column(s): ' . implode(', ', $missingColumns),
            ]]];
        }

        $rows = [];
        $rowNumber = 1; // header occupies row 1

        foreach ($data as $rawRow) {
            $rowNumber++;

            $nonEmptyCells = array_filter($rawRow, static fn($value): bool => trim((string)$value) !== '');
            if ($nonEmptyCells === []) {
                continue;
            }

            $assoc = ['_row' => $rowNumber];
            foreach ($header as $index => $columnName) {
                if ($columnName === '') {
                    continue;
                }
                $assoc[$columnName] = trim((string)($rawRow[$index] ?? ''));
            }

            $rows[] = $assoc;
        }

        return ['rows' => $rows, 'errors' => []];
    }

    /**
     * @param list<array<string, string>> $rows
     * @return array{
     *     mode: string, dryRun: bool, totalRows: int, validRows: int,
     *     inserted: int, updated: int, skipped: int, deletedForReplace: int,
     *     topics: list<string>, errors: list<array{row: int, message: string}>
     * }
     */
    public function import(array $rows, string $mode, int $pid, bool $dryRun): array
    {
        if (!in_array($mode, self::MODES, true)) {
            throw new \InvalidArgumentException('Unknown import mode: ' . $mode, 1751900001);
        }

        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_pipliobackend_word');

        $errors = [];
        $valid = [];

        foreach ($rows as $row) {
            $rowNumber = (int)($row['_row'] ?? 0);
            $topic = strtolower(trim($row['topic'] ?? ''));
            $difficulty = strtolower(trim($row['difficulty'] ?? ''));
            $word = trim($row['word'] ?? '');

            if (!in_array($topic, WordTopics::ALLOWED_TOPICS, true)) {
                $errors[] = ['row' => $rowNumber, 'message' => sprintf('Unknown topic "%s".', $topic)];
                continue;
            }
            if (!in_array($difficulty, WordTopics::ALLOWED_DIFFICULTIES, true)) {
                $errors[] = ['row' => $rowNumber, 'message' => sprintf('Unknown difficulty "%s".', $difficulty)];
                continue;
            }
            if ($word === '') {
                $errors[] = ['row' => $rowNumber, 'message' => 'Field "word" is empty.'];
                continue;
            }

            $valid[] = $this->buildFieldValues($row, $topic, $difficulty, $word);
        }

        $topicsInFile = array_values(array_unique(array_map(
            static fn(array $fields): string => $fields['topic'],
            $valid
        )));

        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $deletedForReplace = 0;

        if ($mode === self::MODE_REPLACE && $topicsInFile !== []) {
            $deletedForReplace = $this->countExisting($connection, $topicsInFile);
            if (!$dryRun) {
                $queryBuilder = $connection->createQueryBuilder();
                $queryBuilder->delete('tx_pipliobackend_word')
                    ->where($queryBuilder->expr()->in(
                        'topic',
                        $queryBuilder->createNamedParameter($topicsInFile, Connection::PARAM_STR_ARRAY)
                    ))
                    ->executeStatement();
            }
        }

        $seenKeys = [];

        foreach ($valid as $fields) {
            $key = $fields['topic'] . '|' . $fields['difficulty'] . '|' . $fields['word'];

            if ($mode === self::MODE_REPLACE) {
                // Matching topics were just cleared above (or would be, outside dry-run),
                // so only a duplicate within this same file can still collide.
                if (isset($seenKeys[$key])) {
                    $skipped++;
                    continue;
                }
                $seenKeys[$key] = true;
                if (!$dryRun) {
                    $connection->insert('tx_pipliobackend_word', $this->withInsertMeta($fields, $pid));
                }
                $inserted++;
                continue;
            }

            $existingUid = $this->findExistingUid($connection, $fields);
            // In dry-run nothing is actually written, so a duplicate key appearing twice
            // in the same file must be simulated via $seenKeys instead of a fresh DB lookup.
            $existsEffective = $existingUid !== null || ($dryRun && isset($seenKeys[$key]));
            $seenKeys[$key] = true;

            if ($mode === self::MODE_APPEND) {
                if ($existsEffective) {
                    $skipped++;
                    continue;
                }
                if (!$dryRun) {
                    $connection->insert('tx_pipliobackend_word', $this->withInsertMeta($fields, $pid));
                }
                $inserted++;
                continue;
            }

            // upsert
            if ($existsEffective) {
                if (!$dryRun && $existingUid !== null) {
                    $connection->update(
                        'tx_pipliobackend_word',
                        $this->withUpdateMeta($fields),
                        ['uid' => $existingUid]
                    );
                }
                $updated++;
            } else {
                if (!$dryRun) {
                    $connection->insert('tx_pipliobackend_word', $this->withInsertMeta($fields, $pid));
                }
                $inserted++;
            }
        }

        return [
            'mode' => $mode,
            'dryRun' => $dryRun,
            'totalRows' => count($rows),
            'validRows' => count($valid),
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'deletedForReplace' => $deletedForReplace,
            'topics' => $topicsInFile,
            'errors' => $errors,
        ];
    }

    /**
     * @return array<string, int|string>
     */
    private function buildFieldValues(array $row, string $topic, string $difficulty, string $word): array
    {
        return [
            'topic' => $topic,
            'difficulty' => $difficulty,
            'word' => $word,
            'artikel' => (string)($row['artikel'] ?? ''),
            'word_type' => (string)($row['word_type'] ?? ''),
            'is_nomen' => $this->parseBool($row['is_nomen'] ?? ''),
            'plural_form' => (string)($row['plural_form'] ?? ''),
            'rhyme_words' => (string)($row['rhyme_words'] ?? ''),
            'no_rhyme_words' => (string)($row['no_rhyme_words'] ?? ''),
            'wrong_options' => (string)($row['wrong_options'] ?? ''),
            'correct' => (string)($row['correct'] ?? ''),
            'full_sentence' => (string)($row['full_sentence'] ?? ''),
            'tense_when' => (string)($row['tense_when'] ?? ''),
            'tense_form' => (string)($row['tense_form'] ?? ''),
            'syllables' => $this->parseInt($row['syllables'] ?? ''),
            'punctuation_mark' => (string)($row['punctuation_mark'] ?? ''),
            'hidden' => $this->parseBool($row['hidden'] ?? ''),
        ];
    }

    private function withInsertMeta(array $fields, int $pid): array
    {
        $now = time();

        return array_merge($fields, [
            'pid' => $pid,
            'tstamp' => $now,
            'crdate' => $now,
            'deleted' => 0,
        ]);
    }

    private function withUpdateMeta(array $fields): array
    {
        return array_merge($fields, [
            'tstamp' => time(),
        ]);
    }

    private function parseBool(mixed $value): int
    {
        $normalized = strtolower(trim((string)$value));
        return in_array($normalized, ['1', 'true', 'ja', 'x', 'yes'], true) ? 1 : 0;
    }

    private function parseInt(mixed $value): int
    {
        $normalized = trim((string)$value);
        return $normalized !== '' && is_numeric($normalized) ? (int)$normalized : 0;
    }

    private function findExistingUid(Connection $connection, array $fields): ?int
    {
        $queryBuilder = $connection->createQueryBuilder();
        $uid = $queryBuilder->select('uid')
            ->from('tx_pipliobackend_word')
            ->where(
                $queryBuilder->expr()->eq('topic', $queryBuilder->createNamedParameter($fields['topic'])),
                $queryBuilder->expr()->eq('difficulty', $queryBuilder->createNamedParameter($fields['difficulty'])),
                $queryBuilder->expr()->eq('word', $queryBuilder->createNamedParameter($fields['word'])),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        return $uid !== false ? (int)$uid : null;
    }

    private function countExisting(Connection $connection, array $topics): int
    {
        $queryBuilder = $connection->createQueryBuilder();

        return (int)$queryBuilder->count('*')
            ->from('tx_pipliobackend_word')
            ->where(
                $queryBuilder->expr()->in('topic', $queryBuilder->createNamedParameter($topics, Connection::PARAM_STR_ARRAY)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchOne();
    }
}
