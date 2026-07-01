<?php
return [
    'ctrl' => [
        'title'            => 'Piplio Wort',
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
            'label'  => 'Versteckt',
            'config' => ['type' => 'check', 'renderType' => 'checkboxToggle'],
        ],

        'topic' => [
            'label'  => 'Thema',
            'config' => [
                'type'       => 'select',
                'renderType' => 'selectSingle',
                'items'      => [
                    ['label' => '-- Thema wählen --', 'value' => ''],
                    ['label' => 'Artikel (der/die/das)',     'value' => 'deutsch_artikel'],
                    ['label' => 'Reime',                     'value' => 'deutsch_reime'],
                    ['label' => 'Groß- & Kleinschreibung',  'value' => 'deutsch_gross_klein'],
                    ['label' => 'Wortarten',                 'value' => 'deutsch_wortarten'],
                    ['label' => 'Plural (Mehrzahl)',         'value' => 'deutsch_plural'],
                ],
                'required' => true,
            ],
        ],

        'difficulty' => [
            'label'  => 'Schwierigkeitsgrad',
            'config' => [
                'type'       => 'select',
                'renderType' => 'selectSingle',
                'items'      => [
                    ['label' => 'Leicht',   'value' => 'easy'],
                    ['label' => 'Mittel',   'value' => 'medium'],
                    ['label' => 'Schwer',   'value' => 'hard'],
                ],
                'default'  => 'easy',
                'required' => true,
            ],
        ],

        'word' => [
            'label'  => 'Wort',
            'config' => [
                'type'     => 'input',
                'size'     => 30,
                'required' => true,
                'eval'     => 'trim',
            ],
        ],

        // ── ARTIKEL ───────────────────────────────────────────────────────────
        'artikel' => [
            'label'       => 'Artikel',
            'displayCond' => 'FIELD:topic:=:deutsch_artikel',
            'config'      => [
                'type'       => 'select',
                'renderType' => 'selectSingle',
                'items'      => [
                    ['label' => '-- wählen --', 'value' => ''],
                    ['label' => 'der', 'value' => 'der'],
                    ['label' => 'die', 'value' => 'die'],
                    ['label' => 'das', 'value' => 'das'],
                ],
            ],
        ],

        // ── WORTARTEN ─────────────────────────────────────────────────────────
        'word_type' => [
            'label'       => 'Wortart',
            'displayCond' => 'FIELD:topic:=:deutsch_wortarten',
            'config'      => [
                'type'       => 'select',
                'renderType' => 'selectSingle',
                'items'      => [
                    ['label' => '-- wählen --',  'value' => ''],
                    ['label' => 'Nomen',    'value' => 'Nomen'],
                    ['label' => 'Verb',     'value' => 'Verb'],
                    ['label' => 'Adjektiv', 'value' => 'Adjektiv'],
                ],
            ],
        ],

        // ── GROSS / KLEIN ─────────────────────────────────────────────────────
        'is_nomen' => [
            'label'       => 'Ist ein Nomen (groß schreiben)',
            'displayCond' => 'FIELD:topic:=:deutsch_gross_klein',
            'config'      => [
                'type'       => 'check',
                'renderType' => 'checkboxToggle',
                'default'    => 0,
            ],
        ],

        // ── PLURAL ────────────────────────────────────────────────────────────
        'plural_form' => [
            'label'       => 'Mehrzahl (Plural)',
            'displayCond' => 'FIELD:topic:=:deutsch_plural',
            'config'      => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],

        // ── REIME ─────────────────────────────────────────────────────────────
        'rhyme_words' => [
            'label'       => 'Reimwörter (kommagetrennt, z.B. Haus,raus,Maus)',
            'displayCond' => 'FIELD:topic:=:deutsch_reime',
            'config'      => [
                'type' => 'input',
                'size' => 60,
                'eval' => 'trim',
            ],
        ],
        'no_rhyme_words' => [
            'label'       => 'Nicht-Reimwörter (kommagetrennt, z.B. Ball,Hund,Baum)',
            'displayCond' => 'FIELD:topic:=:deutsch_reime',
            'config'      => [
                'type' => 'input',
                'size' => 60,
                'eval' => 'trim',
            ],
        ],

        // ── FALSCHE ANTWORTEN (Artikel + Plural) ──────────────────────────────
        'wrong_options' => [
            'label'       => 'Falsche Antworten (kommagetrennt)',
            'displayCond' => 'FIELD:topic:IN:deutsch_plural,deutsch_artikel',
            'config'      => [
                'type' => 'input',
                'size' => 60,
                'eval' => 'trim',
            ],
        ],
    ],

    'types' => [
        '0' => [
            'showitem' => '
                --div--;Allgemein, hidden, topic, difficulty, word,
                --div--;Thema-Details, artikel, word_type, is_nomen, plural_form, rhyme_words, no_rhyme_words, wrong_options
            ',
        ],
    ],
];
