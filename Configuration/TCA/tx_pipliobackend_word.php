<?php
return [
    'ctrl' => [
        'title'            => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.word.title',
        'label'            => 'word',
        'label_alt'        => 'topic,difficulty',
        'label_alt_force'  => true,
        'tstamp'           => 'tstamp',
        'crdate'           => 'crdate',
        'delete'           => 'deleted',
        'enablecolumns'    => ['disabled' => 'hidden'],
        'security'         => [
            'ignorePageTypeRestriction' => true,
        ],
        'searchFields'     => 'word,plural_form',
        'iconfile'         => 'EXT:piplio_backend/Resources/Public/Icons/Extension.svg',
        'default_sortby'   => 'topic ASC, difficulty ASC, word ASC',
    ],

    'columns' => [
        'hidden' => [
            'label'  => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.word.hidden',
            'config' => ['type' => 'check', 'renderType' => 'checkboxToggle'],
        ],

        'topic' => [
            'label'  => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.word.topic',
            'config' => [
                'type'       => 'select',
                'renderType' => 'selectSingle',
                'items'      => [
                    ['label' => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.word.topicPlaceholder', 'value' => ''],
                    ['label' => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:topic.deutsch_artikel', 'value' => 'deutsch_artikel'],
                    ['label' => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:topic.deutsch_reime', 'value' => 'deutsch_reime'],
                    ['label' => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:topic.deutsch_gross_klein', 'value' => 'deutsch_gross_klein'],
                    ['label' => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:topic.deutsch_wortarten', 'value' => 'deutsch_wortarten'],
                    ['label' => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:topic.deutsch_plural', 'value' => 'deutsch_plural'],
                    ['label' => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:topic.deutsch_rechtschreibung', 'value' => 'deutsch_rechtschreibung'],
                    ['label' => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:topic.deutsch_zeitformen', 'value' => 'deutsch_zeitformen'],
                    ['label' => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:topic.deutsch_silben', 'value' => 'deutsch_silben'],
                    ['label' => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:topic.deutsch_satzzeichen', 'value' => 'deutsch_satzzeichen'],
                ],
                'required' => true,
            ],
        ],

        'difficulty' => [
            'label'  => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.word.difficulty',
            'config' => [
                'type'       => 'select',
                'renderType' => 'selectSingle',
                'items'      => [
                    ['label' => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:difficulty.easy', 'value' => 'easy'],
                    ['label' => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:difficulty.medium', 'value' => 'medium'],
                    ['label' => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:difficulty.hard', 'value' => 'hard'],
                    ['label' => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:difficulty.all', 'value' => 'all'],
                ],
                'default'  => 'easy',
                'required' => true,
            ],
        ],

        'word' => [
            'label'       => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.word.word',
            'description' => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.word.wordHint',
            'config'      => [
                'type'     => 'input',
                'size'     => 30,
                'required' => true,
                'eval'     => 'trim',
            ],
        ],

        // ── ARTIKEL ───────────────────────────────────────────────────────────
        'artikel' => [
            'label'       => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.word.artikel',
            'displayCond' => 'FIELD:topic:=:deutsch_artikel',
            'config'      => [
                'type'       => 'select',
                'renderType' => 'selectSingle',
                'items'      => [
                    ['label' => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.word.artikelPlaceholder', 'value' => ''],
                    ['label' => 'der', 'value' => 'der'],
                    ['label' => 'die', 'value' => 'die'],
                    ['label' => 'das', 'value' => 'das'],
                ],
            ],
        ],

        // ── WORTARTEN ─────────────────────────────────────────────────────────
        'word_type' => [
            'label'       => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.word.wordType',
            'displayCond' => 'FIELD:topic:=:deutsch_wortarten',
            'config'      => [
                'type'       => 'select',
                'renderType' => 'selectSingle',
                'items'      => [
                    ['label' => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.word.artikelPlaceholder',  'value' => ''],
                    ['label' => 'Nomen',    'value' => 'Nomen'],
                    ['label' => 'Verb',     'value' => 'Verb'],
                    ['label' => 'Adjektiv', 'value' => 'Adjektiv'],
                ],
            ],
        ],

        // ── GROSS / KLEIN ─────────────────────────────────────────────────────
        'is_nomen' => [
            'label'       => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.word.isNomen',
            'displayCond' => 'FIELD:topic:=:deutsch_gross_klein',
            'config'      => [
                'type'       => 'check',
                'renderType' => 'checkboxToggle',
                'default'    => 0,
            ],
        ],

        // ── PLURAL ────────────────────────────────────────────────────────────
        'plural_form' => [
            'label'       => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.word.pluralForm',
            'displayCond' => 'FIELD:topic:=:deutsch_plural',
            'config'      => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],

        // ── REIME ─────────────────────────────────────────────────────────────
        'rhyme_words' => [
            'label'       => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.word.rhymeWords',
            'displayCond' => 'FIELD:topic:=:deutsch_reime',
            'config'      => [
                'type' => 'input',
                'size' => 60,
                'eval' => 'trim',
            ],
        ],
        'no_rhyme_words' => [
            'label'       => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.word.noRhymeWords',
            'displayCond' => 'FIELD:topic:=:deutsch_reime',
            'config'      => [
                'type' => 'input',
                'size' => 60,
                'eval' => 'trim',
            ],
        ],

        // ── FALSCHE ANTWORTEN (Plural + Rechtschreibung) ─────────────────────
        'wrong_options' => [
            'label'       => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.word.wrongOptions',
            'displayCond' => 'FIELD:topic:IN:deutsch_plural,deutsch_rechtschreibung',
            'config'      => [
                'type' => 'input',
                'size' => 60,
                'eval' => 'trim',
            ],
        ],

        // ── RECHTSCHREIBUNG ────────────────────────────────────────────────────
        'correct' => [
            'label'       => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.word.correct',
            'displayCond' => 'FIELD:topic:=:deutsch_rechtschreibung',
            'config'      => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'full_sentence' => [
            'label'       => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.word.fullSentence',
            'displayCond' => 'FIELD:topic:=:deutsch_rechtschreibung',
            'config'      => [
                'type' => 'input',
                'size' => 60,
                'eval' => 'trim',
            ],
        ],

        // ── ZEITFORMEN ─────────────────────────────────────────────────────────
        'tense_when' => [
            'label'       => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.word.tenseWhen',
            'displayCond' => 'FIELD:topic:=:deutsch_zeitformen',
            'config'      => [
                'type'       => 'select',
                'renderType' => 'selectSingle',
                'items'      => [
                    ['label' => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.word.artikelPlaceholder', 'value' => ''],
                    ['label' => 'Gegenwart', 'value' => 'Gegenwart'],
                    ['label' => 'Vergangenheit', 'value' => 'Vergangenheit'],
                    ['label' => 'Zukunft', 'value' => 'Zukunft'],
                ],
            ],
        ],
        'tense_form' => [
            'label'       => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.word.tenseForm',
            'displayCond' => 'FIELD:topic:=:deutsch_zeitformen',
            'config'      => [
                'type'       => 'select',
                'renderType' => 'selectSingle',
                'items'      => [
                    ['label' => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.word.tenseFormNone', 'value' => ''],
                    ['label' => 'Präsens', 'value' => 'Präsens'],
                    ['label' => 'Präteritum', 'value' => 'Präteritum'],
                    ['label' => 'Perfekt', 'value' => 'Perfekt'],
                ],
            ],
        ],

        // ── SILBEN ───────────────────────────────────────────────────────────
        'syllables' => [
            'label'       => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.word.syllables',
            'displayCond' => 'FIELD:topic:=:deutsch_silben',
            'config'      => [
                'type'    => 'number',
                'format'  => 'integer',
                'default' => 1,
            ],
        ],

        // ── SATZZEICHEN ──────────────────────────────────────────────────────
        'punctuation_mark' => [
            'label'       => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.word.punctuationMark',
            'displayCond' => 'FIELD:topic:=:deutsch_satzzeichen',
            'config'      => [
                'type'       => 'select',
                'renderType' => 'selectSingle',
                'items'      => [
                    ['label' => 'LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.word.artikelPlaceholder', 'value' => ''],
                    ['label' => '.', 'value' => '.'],
                    ['label' => '?', 'value' => '?'],
                    ['label' => '!', 'value' => '!'],
                ],
            ],
        ],
    ],

    'types' => [
        '0' => [
            'showitem' => '
                --div--;LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.tab.general, hidden, topic, difficulty, word,
                --div--;LLL:EXT:piplio_backend/Resources/Private/Language/locallang.xlf:tca.tab.topicDetails, artikel, word_type, is_nomen, plural_form, rhyme_words, no_rhyme_words, wrong_options, correct, full_sentence, tense_when, tense_form, syllables, punctuation_mark
            ',
        ],
    ],
];
