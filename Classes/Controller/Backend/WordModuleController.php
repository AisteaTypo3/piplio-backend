<?php

declare(strict_types=1);

namespace Aistea\PiplioBackend\Controller\Backend;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\ResponseFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

final class WordModuleController extends ActionController
{
    private const TOPIC_LABELS = [
        'deutsch_artikel' => 'topic.deutsch_artikel',
        'deutsch_reime' => 'topic.deutsch_reime',
        'deutsch_gross_klein' => 'topic.deutsch_gross_klein',
        'deutsch_wortarten' => 'topic.deutsch_wortarten',
        'deutsch_plural' => 'topic.deutsch_plural',
    ];

    private const DIFFICULTY_LABELS = [
        'easy' => 'difficulty.easy',
        'medium' => 'difficulty.medium',
        'hard' => 'difficulty.hard',
    ];

    public function indexAction(string $topic = '', string $difficulty = '', string $q = ''): ResponseInterface
    {
        $filters = [
            'topic' => trim($topic),
            'difficulty' => trim($difficulty),
            'q' => trim($q),
        ];

        $moduleTemplate = $this->getModuleTemplateFactory()->create($this->request);
        $moduleTemplate->setTitle($this->translate('module.title'));

        $buttonBar = $moduleTemplate->getDocHeaderComponent()->getButtonBar();
        $shortcutButton = $buttonBar->makeShortcutButton()
            ->setRouteIdentifier('piplio_backend')
            ->setDisplayName($this->translate('module.title'));
        $buttonBar->addButton($shortcutButton, ButtonBar::BUTTON_POSITION_RIGHT);

        $moduleTemplate->assignMultiple([
            'filters' => $filters,
            'stats' => $this->buildStats(),
            'interestStats' => $this->buildInterestStats(),
            'topInterestPages' => $this->findTopInterestPages(),
            'topicOptions' => $this->buildOptions(self::TOPIC_LABELS, $filters['topic'], $this->translate('backend.filter.allTopics')),
            'difficultyOptions' => $this->buildOptions(self::DIFFICULTY_LABELS, $filters['difficulty'], $this->translate('backend.filter.allLevels')),
            'rows' => $this->findWords($filters),
            'recentInterests' => $this->findRecentInterests(),
            'apiExamples' => $this->buildApiExamples(),
        ]);

        return $moduleTemplate->renderResponse('Backend/Word/Index');
    }

    public function exportInterestsAction(): ResponseInterface
    {
        $rows = $this->findInterestsForExport();
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('Unable to create temporary export stream.', 1751566501);
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            'uid',
            'name',
            'email',
            'page_title',
            'page_url',
            'source_page_id',
            'privacy_version',
            'consent_timestamp',
            'created_at',
            'hidden',
        ], ';');

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['uid'],
                $row['name'],
                $row['email'],
                $row['page_title'],
                $row['page_url'],
                $row['source_page_id'],
                $row['privacy_version'],
                $row['consent_timestamp'] > 0 ? date('c', (int)$row['consent_timestamp']) : '',
                $row['crdate'] > 0 ? date('c', (int)$row['crdate']) : '',
                (int)$row['hidden'],
            ], ';');
        }

        rewind($handle);
        $csv = (string)stream_get_contents($handle);
        fclose($handle);

        $response = $this->getResponseFactory()->createResponse(200)
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="piplio-interest-export.csv"');

        $response->getBody()->write($csv);

        return $response;
    }

    public function deleteInterestAction(int $interestUid): ResponseInterface
    {
        if ($interestUid > 0) {
            $connection = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getConnectionForTable('tx_pipliobackend_interest');

            $affectedRows = $connection->update(
                'tx_pipliobackend_interest',
                [
                    'deleted' => 1,
                    'tstamp' => time(),
                ],
                [
                    'uid' => $interestUid,
                    'deleted' => 0,
                ],
                [
                    'deleted' => Connection::PARAM_INT,
                    'tstamp' => Connection::PARAM_INT,
                    'uid' => Connection::PARAM_INT,
                ]
            );

            if ($affectedRows > 0) {
                $this->addFlashMessage($this->translate('backend.deletedSuccess'));
            } else {
                $this->addFlashMessage($this->translate('backend.deletedMissing'), '', \TYPO3\CMS\Core\Type\ContextualFeedbackSeverity::WARNING);
            }
        }

        return $this->redirect('index');
    }

    private function buildStats(): array
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_pipliobackend_word');

        $total = (int)$connection->count('*', 'tx_pipliobackend_word', []);
        $visible = (int)$connection->count('*', 'tx_pipliobackend_word', ['hidden' => 0, 'deleted' => 0]);
        $hidden = (int)$connection->count('*', 'tx_pipliobackend_word', ['hidden' => 1, 'deleted' => 0]);

        return [
            'total' => $total,
            'visible' => $visible,
            'hidden' => $hidden,
            'topics' => $this->countBy('topic', self::TOPIC_LABELS),
            'difficulties' => $this->countBy('difficulty', self::DIFFICULTY_LABELS),
        ];
    }

    private function countBy(string $field, array $labels): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_pipliobackend_word');

        $rows = $queryBuilder
            ->select($field)
            ->addSelectLiteral('COUNT(*) AS record_count')
            ->from('tx_pipliobackend_word')
            ->where(
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \TYPO3\CMS\Core\Database\Connection::PARAM_INT))
            )
            ->groupBy($field)
            ->orderBy($field)
            ->executeQuery()
            ->fetchAllAssociative();

        $items = [];
        foreach ($rows as $row) {
            $value = (string)($row[$field] ?? '');
            $items[] = [
                'value' => $value,
                'label' => $this->translate($labels[$value] ?? $value),
                'count' => (int)($row['record_count'] ?? 0),
            ];
        }

        return $items;
    }

    private function buildInterestStats(): array
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_pipliobackend_interest');

        $total = (int)$connection->count('*', 'tx_pipliobackend_interest', ['deleted' => 0]);
        $visible = (int)$connection->count('*', 'tx_pipliobackend_interest', ['hidden' => 0, 'deleted' => 0]);
        $last24Hours = $this->countInterestSince(time() - 86400);
        $last7Days = $this->countInterestSince(time() - 604800);

        return [
            'total' => $total,
            'visible' => $visible,
            'last24Hours' => $last24Hours,
            'last7Days' => $last7Days,
        ];
    }

    private function countInterestSince(int $timestamp): int
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_pipliobackend_interest');

        return (int)$queryBuilder
            ->count('*')
            ->from('tx_pipliobackend_interest')
            ->where(
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \TYPO3\CMS\Core\Database\Connection::PARAM_INT)),
                $queryBuilder->expr()->gte('crdate', $queryBuilder->createNamedParameter($timestamp, \TYPO3\CMS\Core\Database\Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchOne();
    }

    private function findRecentInterests(): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_pipliobackend_interest');

        $rows = $queryBuilder
            ->select('uid', 'name', 'email', 'page_title', 'page_url', 'source_page_id', 'crdate', 'hidden', 'consent_timestamp', 'privacy_version')
            ->from('tx_pipliobackend_interest')
            ->where(
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \TYPO3\CMS\Core\Database\Connection::PARAM_INT))
            )
            ->orderBy('crdate', 'DESC')
            ->setMaxResults(10)
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(static fn(array $row): array => [
            'uid' => (int)($row['uid'] ?? 0),
            'name' => (string)($row['name'] ?? ''),
            'email' => (string)($row['email'] ?? ''),
            'pageTitle' => (string)($row['page_title'] ?? ''),
            'pageUrl' => (string)($row['page_url'] ?? ''),
            'sourcePageId' => (int)($row['source_page_id'] ?? 0),
            'createdAt' => (int)($row['crdate'] ?? 0),
            'consentTimestamp' => (int)($row['consent_timestamp'] ?? 0),
            'privacyVersion' => (string)($row['privacy_version'] ?? ''),
            'isHidden' => (bool)($row['hidden'] ?? false),
        ], $rows);
    }

    private function findInterestsForExport(): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_pipliobackend_interest');

        return $queryBuilder
            ->select('uid', 'name', 'email', 'page_title', 'page_url', 'source_page_id', 'crdate', 'hidden', 'consent_timestamp', 'privacy_version')
            ->from('tx_pipliobackend_interest')
            ->where(
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->orderBy('crdate', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    private function findTopInterestPages(): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_pipliobackend_interest');

        $rows = $queryBuilder
            ->select('page_title', 'page_url', 'source_page_id')
            ->addSelectLiteral('COUNT(*) AS lead_count')
            ->from('tx_pipliobackend_interest')
            ->where(
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \TYPO3\CMS\Core\Database\Connection::PARAM_INT))
            )
            ->groupBy('page_title', 'page_url', 'source_page_id')
            ->orderBy('lead_count', 'DESC')
            ->addOrderBy('page_title', 'ASC')
            ->setMaxResults(5)
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(static fn(array $row): array => [
            'pageTitle' => (string)($row['page_title'] ?? ''),
            'pageUrl' => (string)($row['page_url'] ?? ''),
            'sourcePageId' => (int)($row['source_page_id'] ?? 0),
            'leadCount' => (int)($row['lead_count'] ?? 0),
        ], $rows);
    }

    private function findWords(array $filters): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_pipliobackend_word');

        $queryBuilder
            ->select(
                'uid',
                'pid',
                'hidden',
                'topic',
                'difficulty',
                'word',
                'artikel',
                'word_type',
                'is_nomen',
                'plural_form',
                'rhyme_words',
                'no_rhyme_words',
                'wrong_options',
                'tstamp'
            )
            ->from('tx_pipliobackend_word')
            ->where(
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \TYPO3\CMS\Core\Database\Connection::PARAM_INT))
            );

        if ($filters['topic'] !== '') {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq('topic', $queryBuilder->createNamedParameter($filters['topic']))
            );
        }

        if ($filters['difficulty'] !== '') {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq('difficulty', $queryBuilder->createNamedParameter($filters['difficulty']))
            );
        }

        if ($filters['q'] !== '') {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->like(
                    'word',
                    $queryBuilder->createNamedParameter('%' . $queryBuilder->escapeLikeWildcards($filters['q']) . '%')
                )
            );
        }

        $rows = $queryBuilder
            ->orderBy('topic')
            ->addOrderBy('difficulty')
            ->addOrderBy('word')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(fn(array $row): array => $this->formatRow($row), $rows);
    }

    private function formatRow(array $row): array
    {
        $details = match ((string)$row['topic']) {
            'deutsch_artikel' => sprintf($this->translate('details.artikel'), $row['artikel'] ?: $this->translate('details.default')),
            'deutsch_reime' => sprintf($this->translate('details.reime'), $this->limitList((string)$row['rhyme_words']), $this->limitList((string)$row['no_rhyme_words'])),
            'deutsch_gross_klein' => (bool)$row['is_nomen'] ? $this->translate('details.grossKlein.nomen') : $this->translate('details.grossKlein.other'),
            'deutsch_wortarten' => sprintf($this->translate('details.wortart'), $row['word_type'] ?: $this->translate('details.default')),
            'deutsch_plural' => sprintf($this->translate('details.plural'), $row['plural_form'] ?: $this->translate('details.default'), $this->limitList((string)$row['wrong_options'])),
            default => $this->translate('details.default'),
        };

        return [
            'uid' => (int)$row['uid'],
            'pid' => (int)$row['pid'],
            'word' => (string)$row['word'],
            'topic' => (string)$row['topic'],
            'topicLabel' => $this->translate(self::TOPIC_LABELS[(string)$row['topic']] ?? (string)$row['topic']),
            'difficulty' => (string)$row['difficulty'],
            'difficultyLabel' => $this->translate(self::DIFFICULTY_LABELS[(string)$row['difficulty']] ?? (string)$row['difficulty']),
            'isHidden' => (bool)$row['hidden'],
            'statusLabel' => (bool)$row['hidden'] ? $this->translate('status.hidden') : $this->translate('status.visible'),
            'details' => $details,
            'updatedAt' => (int)$row['tstamp'],
        ];
    }

    private function buildOptions(array $labels, string $activeValue, string $allLabel): array
    {
        $options = [[
            'value' => '',
            'label' => $allLabel,
            'selected' => $activeValue === '',
        ]];

        foreach ($labels as $value => $label) {
            $options[] = [
                'value' => $value,
                'label' => $label,
                'selected' => $activeValue === $value,
            ];
        }

        return $options;
    }

    private function buildApiExamples(): array
    {
        return [
            '/api/piplio/words',
            '/api/piplio/words?topic=deutsch_artikel',
            '/api/piplio/words?topic=deutsch_plural&difficulty=easy',
            '/api/piplio/words?topic=deutsch_reime&difficulty=medium',
        ];
    }

    private function limitList(string $value): string
    {
        $items = array_values(array_filter(array_map('trim', explode(',', $value))));
        if ($items === []) {
            return $this->translate('details.default');
        }

        if (count($items) <= 3) {
            return implode(', ', $items);
        }

        return implode(', ', array_slice($items, 0, 3)) . ' ...';
    }

    private function getModuleTemplateFactory(): ModuleTemplateFactory
    {
        return GeneralUtility::makeInstance(ModuleTemplateFactory::class);
    }

    private function getResponseFactory(): ResponseFactory
    {
        return GeneralUtility::makeInstance(ResponseFactory::class);
    }

    private function translate(string $key): string
    {
        return (string)(LocalizationUtility::translate($key, 'PiplioBackend') ?? $key);
    }
}
