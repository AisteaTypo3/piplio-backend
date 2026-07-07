<?php

declare(strict_types=1);

namespace Aistea\PiplioBackend\Utility;

/**
 * Single source of truth for what the tx_pipliobackend_word table may contain,
 * shared between the API middleware and the spreadsheet importer so both always
 * agree on what a valid topic/difficulty/column looks like.
 */
final class WordTopics
{
    public const ALLOWED_TOPICS = [
        'deutsch_artikel',
        'deutsch_reime',
        'deutsch_gross_klein',
        'deutsch_wortarten',
        'deutsch_plural',
        'deutsch_rechtschreibung',
        'deutsch_zeitformen',
        'deutsch_silben',
        'deutsch_satzzeichen',
    ];

    public const ALLOWED_DIFFICULTIES = ['easy', 'medium', 'hard', 'all'];

    /**
     * Column order used by both the import template and the parser. One row = one
     * tx_pipliobackend_word record; columns not relevant to a given topic are left empty.
     */
    public const IMPORT_COLUMNS = [
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
        'correct',
        'full_sentence',
        'tense_when',
        'tense_form',
        'syllables',
        'punctuation_mark',
        'hidden',
    ];

    /** Columns compared to detect an existing record (import "upsert"/"replace" match key). */
    public const UNIQUE_KEY_COLUMNS = ['topic', 'difficulty', 'word'];
}
