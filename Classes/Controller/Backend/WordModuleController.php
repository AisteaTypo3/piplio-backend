<?php

declare(strict_types=1);

namespace Aistea\PiplioBackend\Controller\Backend;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class WordModuleController extends ActionController
{
    private const TOPIC_LABELS = [
        'deutsch_artikel' => 'Artikel',
        'deutsch_reime' => 'Reime',
        'deutsch_gross_klein' => 'Gross/Klein',
        'deutsch_wortarten' => 'Wortarten',
        'deutsch_plural' => 'Plural',
    ];

    private const DIFFICULTY_LABELS = [
        'easy' => 'Leicht',
        'medium' => 'Mittel',
        'hard' => 'Schwer',
    ];

    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
    ) {}

    public function indexAction(string $topic = '', string $difficulty = '', string $q = ''): ResponseInterface
    {
        $filters = [
            'topic' => trim($topic),
            'difficulty' => trim($difficulty),
            'q' => trim($q),
        ];

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setTitle('Piplio Daten');

        $buttonBar = $moduleTemplate->getDocHeaderComponent()->getButtonBar();
        $shortcutButton = $buttonBar->makeShortcutButton()
            ->setRouteIdentifier('piplio_backend')
            ->setDisplayName('Piplio Daten');
        $buttonBar->addButton($shortcutButton, ButtonBar::BUTTON_POSITION_RIGHT);

        $moduleTemplate->assignMultiple([
            'filters' => $filters,
            'stats' => $this->buildStats(),
            'topicOptions' => $this->buildOptions(self::TOPIC_LABELS, $filters['topic'], 'Alle Themen'),
            'difficultyOptions' => $this->buildOptions(self::DIFFICULTY_LABELS, $filters['difficulty'], 'Alle Level'),
            'rows' => $this->findWords($filters),
            'apiExamples' => $this->buildApiExamples(),
        ]);

        return $moduleTemplate->renderResponse('Backend/Word/Index');
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
                'label' => $labels[$value] ?? $value,
                'count' => (int)($row['record_count'] ?? 0),
            ];
        }

        return $items;
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
            'deutsch_artikel' => 'Artikel: ' . ($row['artikel'] ?: '-'),
            'deutsch_reime' => 'Reime: ' . $this->limitList((string)$row['rhyme_words']) . ' | Keine Reime: ' . $this->limitList((string)$row['no_rhyme_words']),
            'deutsch_gross_klein' => (bool)$row['is_nomen'] ? 'Nomen, soll gross geschrieben werden' : 'Kein Nomen, soll klein geschrieben werden',
            'deutsch_wortarten' => 'Wortart: ' . ($row['word_type'] ?: '-'),
            'deutsch_plural' => 'Plural: ' . ($row['plural_form'] ?: '-') . ' | Falsch: ' . $this->limitList((string)$row['wrong_options']),
            default => '-',
        };

        return [
            'uid' => (int)$row['uid'],
            'pid' => (int)$row['pid'],
            'word' => (string)$row['word'],
            'topic' => (string)$row['topic'],
            'topicLabel' => self::TOPIC_LABELS[(string)$row['topic']] ?? (string)$row['topic'],
            'difficulty' => (string)$row['difficulty'],
            'difficultyLabel' => self::DIFFICULTY_LABELS[(string)$row['difficulty']] ?? (string)$row['difficulty'],
            'isHidden' => (bool)$row['hidden'],
            'statusLabel' => (bool)$row['hidden'] ? 'Versteckt' : 'Sichtbar',
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
            return '-';
        }

        if (count($items) <= 3) {
            return implode(', ', $items);
        }

        return implode(', ', array_slice($items, 0, 3)) . ' ...';
    }
}
