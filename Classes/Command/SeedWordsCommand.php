<?php

declare(strict_types=1);

namespace Aistea\PiplioBackend\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;

#[AsCommand(name: 'piplio:seed', description: 'Seed initial German word data into tx_pipliobackend_word')]
class SeedWordsCommand extends Command
{
    public function __construct(private readonly ConnectionPool $connectionPool)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Re-seed even if data already exists');
        $this->addOption('truncate', 't', InputOption::VALUE_NONE, 'Truncate the table before re-seeding. Only valid together with --force');
        $this->addOption('pid', null, InputOption::VALUE_REQUIRED, 'Page ID to assign records to', 1);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connection = $this->connectionPool->getConnectionForTable('tx_pipliobackend_word');
        $pid        = (int)$input->getOption('pid');
        $force      = (bool)$input->getOption('force');
        $truncate   = (bool)$input->getOption('truncate');

        if ($truncate && !$force) {
            $output->writeln('<error>Use --truncate only together with --force.</error>');
            return Command::INVALID;
        }

        $existing = $connection->count('*', 'tx_pipliobackend_word', ['deleted' => 0]);
        if ($existing > 0 && !$force) {
            $output->writeln("<info>Already {$existing} words in database. Use --force to re-seed.</info>");
            return Command::SUCCESS;
        }

        if ($force) {
            if (!$truncate) {
                $output->writeln('<error>Refusing to replace existing data without --truncate. Use --force --truncate for destructive reseeding.</error>');
                return Command::INVALID;
            }
            $connection->truncate('tx_pipliobackend_word');
            $output->writeln('<comment>Table truncated.</comment>');
        }

        $now   = time();
        $rows  = $this->buildRows($pid, $now);
        $count = 0;

        foreach ($rows as $row) {
            $connection->insert('tx_pipliobackend_word', $row);
            $count++;
        }

        $output->writeln("<info>✓ Seeded {$count} words successfully.</info>");
        return Command::SUCCESS;
    }

    private function buildRows(int $pid, int $now): array
    {
        $base = ['pid' => $pid, 'hidden' => 0, 'deleted' => 0, 'crdate' => $now, 'tstamp' => $now];
        $rows = [];

        $add = function (string $topic, string $difficulty, array $fields) use ($base, &$rows): void {
            $rows[] = array_merge($base, ['topic' => $topic, 'difficulty' => $difficulty], $fields);
        };

        // ── ARTIKEL EASY ──────────────────────────────────────────────────────
        foreach ([
            ['Hund','der'],['Apfel','der'],['Ball','der'],['Vogel','der'],['Tisch','der'],
            ['Mond','der'],['Käse','der'],['Stuhl','der'],['Arm','der'],['Kopf','der'],
            ['Finger','der'],['Fuß','der'],['Zug','der'],['Ring','der'],
            ['Katze','die'],['Maus','die'],['Blume','die'],['Schule','die'],['Sonne','die'],
            ['Nase','die'],['Birne','die'],['Zeit','die'],['Stadt','die'],['Nacht','die'],
            ['Farbe','die'],['Uhr','die'],['Zahl','die'],['Welt','die'],
            ['Haus','das'],['Kind','das'],['Buch','das'],['Auto','das'],['Bett','das'],
            ['Boot','das'],['Wort','das'],['Licht','das'],['Geld','das'],['Feuer','das'],
            ['Gras','das'],['Holz','das'],
        ] as [$w, $a]) {
            $add('deutsch_artikel', 'easy', ['word' => $w, 'artikel' => $a]);
        }

        // ── ARTIKEL MEDIUM ────────────────────────────────────────────────────
        foreach ([
            ['Baum','der'],['Stern','der'],['Schuh','der'],['Kuchen','der'],['Löwe','der'],
            ['Regen','der'],['Schnee','der'],['König','der'],['Berg','der'],['Fluss','der'],
            ['Sand','der'],['Topf','der'],['Turm','der'],['Ast','der'],
            ['Tür','die'],['Hand','die'],['Tasche','die'],['Schnecke','die'],['Giraffe','die'],
            ['Rakete','die'],['Ente','die'],['Wolke','die'],['Wiese','die'],['Stimme','die'],
            ['Tante','die'],['Insel','die'],['Welle','die'],['Feder','die'],
            ['Pferd','das'],['Tier','das'],['Glas','das'],['Huhn','das'],['Eis','das'],
            ['Bild','das'],['Schloss','das'],['Nest','das'],['Loch','das'],['Meer','das'],
            ['Tal','das'],['Ei','das'],
        ] as [$w, $a]) {
            $add('deutsch_artikel', 'medium', ['word' => $w, 'artikel' => $a]);
        }

        // ── ARTIKEL HARD ──────────────────────────────────────────────────────
        foreach ([
            ['Schmetterling','der'],['Regenbogen','der'],['Käfer','der'],['Elefant','der'],
            ['Schrank','der'],['Teppich','der'],['Spiegel','der'],['Hammer','der'],
            ['Kalender','der'],['Pinsel','der'],
            ['Flugzeug','das'],['Fenster','das'],['Fahrrad','das'],['Gesicht','das'],
            ['Abenteuer','das'],['Geheimnis','das'],['Kissen','das'],['Messer','das'],
            ['Regal','das'],['Dach','das'],['Tor','das'],['Werkzeug','das'],
            ['Gabel','die'],['Treppe','die'],['Straße','die'],['Küche','die'],
            ['Brücke','die'],['Zunge','die'],['Schere','die'],['Nadel','die'],
            ['Wand','die'],['Decke','die'],['Flasche','die'],['Pflanze','die'],
            ['Socke','die'],['Lampe','die'],
        ] as [$w, $a]) {
            $add('deutsch_artikel', 'hard', ['word' => $w, 'artikel' => $a]);
        }

        // ── REIME EASY ────────────────────────────────────────────────────────
        foreach ([
            ['Maus',   'Haus,raus',          'Ball,Hund,Baum,Fisch'],
            ['Ball',   'Fall,Hall,Stall',     'Maus,Hund,Baum,Fisch'],
            ['Hund',   'Mund,Bund,rund',      'Ball,Maus,Stern,Baum'],
            ['Tisch',  'Fisch,frisch',        'Haus,Baum,Ball,Hund'],
            ['Baum',   'Traum,Raum,Schaum',   'Hund,Ball,Maus,Fisch'],
            ['Schuh',  'Kuh,Ruh',             'Ball,Maus,Hund,Stern'],
            ['Kind',   'Wind,Rind,blind',     'Ball,Maus,Hund,Buch'],
            ['Bein',   'klein,Stein,fein',    'Ball,Maus,Hund,Baum'],
            ['Ring',   'sing,bring,Ding',     'Ball,Maus,Hund,Baum'],
            ['Hut',    'gut,Mut,Wut',         'Ball,Maus,Hund,Kind'],
            ['Zoo',    'so,wo,froh',          'Ball,Hund,Baum,Fisch'],
            ['Meer',   'mehr,leer,schwer',    'Ball,Maus,Hund,Kind'],
            ['Schnee', 'See,Tee,Fee',         'Ball,Maus,Hund,Baum'],
            ['Tor',    'vor,Rohr,Ohr',        'Ball,Maus,Hund,Baum'],
            ['Zug',    'Krug,klug,Flug',      'Ball,Maus,Hund,Ring'],
        ] as [$w, $r, $n]) {
            $add('deutsch_reime', 'easy', ['word' => $w, 'rhyme_words' => $r, 'no_rhyme_words' => $n]);
        }

        // ── REIME MEDIUM ──────────────────────────────────────────────────────
        foreach ([
            ['Stern',  'fern,Kern,gern',        'Ball,Maus,Baum,Hund'],
            ['Buch',   'Tuch,Fluch,Besuch',     'Ball,Maus,Hund,Stern'],
            ['Nacht',  'Macht,gedacht,Wacht',   'Ball,Maus,Hund,Stern'],
            ['Tag',    'lag,mag,Schlag',         'Ball,Maus,Hund,Stern'],
            ['Eis',    'heiß,weiß,Preis',       'Ball,Maus,Hund,Stern'],
            ['Licht',  'nicht,dicht,Pflicht',   'Ball,Maus,Hund,Stern'],
            ['Hase',   'Nase,Vase,Phase',       'Hund,Ball,Maus,Stern'],
            ['Gabel',  'Kabel,Fabel',           'Hund,Ball,Maus,Stern'],
            ['Blatt',  'glatt,satt,platt',      'Ball,Maus,Hund,Stern'],
            ['Zeit',   'weit,breit,leid',       'Ball,Maus,Hund,Stern'],
            ['Turm',   'Sturm,Wurm',            'Ball,Maus,Hund,Licht'],
            ['Kopf',   'Zopf,Topf',             'Ball,Maus,Hund,Stern'],
            ['Stein',  'Wein,fein,allein',      'Ball,Maus,Hund,Baum'],
            ['Geld',   'Feld,Held,Welt',        'Ball,Maus,Hund,Stern'],
            ['Zahl',   'Wahl,Tal,Mal',          'Ball,Maus,Hund,Stern'],
        ] as [$w, $r, $n]) {
            $add('deutsch_reime', 'medium', ['word' => $w, 'rhyme_words' => $r, 'no_rhyme_words' => $n]);
        }

        // ── GROSS/KLEIN – NOMEN (easy) ────────────────────────────────────────
        foreach (['Hund','Katze','Haus','Schule','Baum','Kind','Vogel','Buch','Ball',
                  'Sonne','Blume','Tisch','Stern','Auto','Stuhl'] as $w) {
            $add('deutsch_gross_klein', 'easy', ['word' => $w, 'is_nomen' => 1]);
        }

        // ── GROSS/KLEIN – VERBEN (medium) ────────────────────────────────────
        foreach (['laufen','spielen','schlafen','trinken','schreiben','singen',
                  'klettern','tanzen','malen','schwimmen','springen','lesen'] as $w) {
            $add('deutsch_gross_klein', 'medium', ['word' => $w, 'is_nomen' => 0]);
        }

        // ── GROSS/KLEIN – ADJEKTIVE (hard) ───────────────────────────────────
        foreach (['groß','klein','schnell','schön','laut','leise','warm','kalt',
                  'hell','dunkel','süß','sauer'] as $w) {
            $add('deutsch_gross_klein', 'hard', ['word' => $w, 'is_nomen' => 0]);
        }

        // ── WORTARTEN ─────────────────────────────────────────────────────────
        foreach ([
            'easy' => [
                'Nomen' => ['Hund','Katze','Haus','Ball','Baum','Sonne','Mond','Buch'],
                'Verb'  => ['laufen','springen','schlafen','singen','malen','hüpfen'],
                'Adjektiv' => ['groß','klein','schnell','schön','warm','alt'],
            ],
            'medium' => [
                'Nomen' => ['Schule','Tisch','Auto','Vogel','Kind','Blume','Stern','König'],
                'Verb'  => ['trinken','schreiben','spielen','lesen','tanzen','kochen'],
                'Adjektiv' => ['leise','kalt','rot','rund','süß','hoch'],
            ],
            'hard' => [
                'Nomen' => ['Schmetterling','Regenbogen','Elefant','Abenteuer','Geheimnis'],
                'Verb'  => ['beobachten','flüstern','entdecken','erklären','verwandeln'],
                'Adjektiv' => ['neugierig','fröhlich','tapfer','mutig','aufgeregt'],
            ],
        ] as $diff => $types) {
            foreach ($types as $type => $words) {
                foreach ($words as $w) {
                    $add('deutsch_wortarten', $diff, ['word' => $w, 'word_type' => $type]);
                }
            }
        }

        // ── PLURAL EASY ───────────────────────────────────────────────────────
        foreach ([
            ['Hund','Hunde','Hunds,Hünde,Hunden'],
            ['Katze','Katzen','Katzs,Kätze,Katzens'],
            ['Auto','Autos','Autoes,Autoen,Äutos'],
            ['Schuh','Schuhe','Schuhs,Schühe,Schuhen'],
            ['Bett','Betten','Betts,Bette,Bötten'],
            ['Tisch','Tische','Tischs,Tischen,Tischem'],
            ['Stern','Sterne','Sterns,Sternen,Sternet'],
            ['Blume','Blumen','Blumes,Blümen,Blumens'],
            ['Arm','Arme','Arms,Ärme,Armen'],
            ['Tag','Tage','Tags,Täge,Tagen'],
            ['Ring','Ringe','Rings,Ränge,Ringen'],
            ['Schaf','Schafe','Schafs,Schäfe,Schafen'],
            ['Affe','Affen','Affes,Äffe,Affens'],
            ['Uhr','Uhren','Uhrs,Ühr,Uhrens'],
            ['Brief','Briefe','Briefs,Bräfe,Briefen'],
            ['Zelt','Zelte','Zelts,Zälte,Zelten'],
            ['Heft','Hefte','Hefts,Häfte,Heften'],
            ['Pilz','Pilze','Pilzs,Pilzen,Pälze'],
        ] as [$s, $p, $w]) {
            $add('deutsch_plural', 'easy', ['word' => $s, 'plural_form' => $p, 'wrong_options' => $w]);
        }

        // ── PLURAL MEDIUM ─────────────────────────────────────────────────────
        foreach ([
            ['Haus','Häuser','Hauser,Häuse,Hauses'],
            ['Kind','Kinder','Kinds,Kindes,Kindere'],
            ['Baum','Bäume','Baums,Baume,Bäumen'],
            ['Buch','Bücher','Buchs,Buche,Büchen'],
            ['Hand','Hände','Hands,Handen,Händes'],
            ['Ball','Bälle','Balls,Balle,Bälles'],
            ['Vogel','Vögel','Vogels,Vogele,Vögels'],
            ['Zug','Züge','Zugs,Zuge,Zügen'],
            ['Wald','Wälder','Walds,Walde,Wäldere'],
            ['Fluss','Flüsse','Flusses,Flüssen,Flussee'],
            ['Topf','Töpfe','Topfs,Topfe,Töpfen'],
            ['Ast','Äste','Asts,Aste,Ästen'],
            ['Hut','Hüte','Huts,Hüten,Hütes'],
        ] as [$s, $p, $w]) {
            $add('deutsch_plural', 'medium', ['word' => $s, 'plural_form' => $p, 'wrong_options' => $w]);
        }

        // ── PLURAL HARD ───────────────────────────────────────────────────────
        foreach ([
            ['Apfel','Äpfel','Apfels,Apfele,Äpfels'],
            ['Maus','Mäuse','Mause,Mausen,Mäusen'],
            ['Stuhl','Stühle','Stuhls,Stuhle,Stühlen'],
            ['Glas','Gläser','Glasen,Gläse,Gläsers'],
            ['Zahn','Zähne','Zahns,Zahne,Zähnse'],
            ['Bruder','Brüder','Bruders,Brüdern,Brudern'],
            ['Nagel','Nägel','Nagels,Nagele,Nägeln'],
            ['Garten','Gärten','Gartens,Gartene,Gärtens'],
            ['Mantel','Mäntel','Mantels,Mantele,Mänteln'],
            ['Hammer','Hämmer','Hammers,Hammere,Hämmern'],
            ['Vater','Väter','Vaters,Vatere,Vätern'],
            ['Laden','Läden','Ladens,Ladene,Lädens'],
        ] as [$s, $p, $w]) {
            $add('deutsch_plural', 'hard', ['word' => $s, 'plural_form' => $p, 'wrong_options' => $w]);
        }

        // ── RECHTSCHREIBUNG EASY ──────────────────────────────────────────────
        foreach ([
            ['Sp__l', 'ie', 'i,ieh', 'Spiel'],
            ['f__l', 'ie', 'i,ieh', 'fiel'],
            ['Fu__ball', 'ß', 'ss,s', 'Fußball'],
            ['Stra__e', 'ß', 'ss,s', 'Straße'],
            ['Ba__', 'll', 'l,lh', 'Ball'],
            ['Ma__e', 'pp', 'p,b', 'Mappe'],
            ['Ke__e', 'tt', 't,d', 'Kette'],
            ['So__e', 'nn', 'n,m', 'Sonne'],
            ['V__gel', 'o', 'a,u', 'Vogel'],
            ['__ater', 'V', 'F,W', 'Vater'],
        ] as [$masked, $correct, $wrong, $full]) {
            $add('deutsch_rechtschreibung', 'easy', ['word' => $masked, 'correct' => $correct, 'wrong_options' => $wrong, 'full_sentence' => $full]);
        }

        // ── RECHTSCHREIBUNG MEDIUM ────────────────────────────────────────────
        foreach ([
            ['Ba__m', 'u', 'au,o', 'Baum'],
            ['H__user', 'äu', 'eu,au', 'Häuser'],
            ['n__', 'eu', 'äu,ei', 'neu'],
            ['L__fer', 'äu', 'eu,ei', 'Läufer'],
            ['Sch__le', 'u', 'ul,uh', 'Schule'],
            ['K__che', 'ü', 'u,ue', 'Küche'],
            ['Fr__hling', 'ü', 'u,ue', 'Frühling'],
            ['gr__n', 'ü', 'u,uh', 'grün'],
            ['sch__n', 'ö', 'o,oe', 'schön'],
            ['K__nig', 'ö', 'o,oe', 'König'],
        ] as [$masked, $correct, $wrong, $full]) {
            $add('deutsch_rechtschreibung', 'medium', ['word' => $masked, 'correct' => $correct, 'wrong_options' => $wrong, 'full_sentence' => $full]);
        }

        // ── RECHTSCHREIBUNG HARD ──────────────────────────────────────────────
        foreach ([
            ['Fu__', 'chs', 'x,cks', 'Fuchs'],
            ['se__s', 'ch', 'k,c', 'sechs'],
            ['Pla__', 'tz', 'z,ts', 'Platz'],
            ['Ka__e', 'tz', 'z,ts', 'Katze'],
            ['W__hnung', 'o', 'oh,ho', 'Wohnung'],
            ['Ze__ung', 'it', 'eit,ieh', 'Zeitung'],
            ['gl__ch', 'ei', 'ie,ai', 'gleich'],
            ['spr__chen', 'e', 'ä,ee', 'sprechen'],
            ['K__rper', 'ö', 'o,oe', 'Körper'],
            ['H__nde', 'ä', 'e,ae', 'Hände'],
        ] as [$masked, $correct, $wrong, $full]) {
            $add('deutsch_rechtschreibung', 'hard', ['word' => $masked, 'correct' => $correct, 'wrong_options' => $wrong, 'full_sentence' => $full]);
        }

        // ── ZEITFORMEN (ein Pool, difficulty=all) ─────────────────────────────
        foreach ([
            ['Ich spiele im Garten.', 'Gegenwart', 'Präsens'],
            ['Sie kauft ein Buch.', 'Gegenwart', 'Präsens'],
            ['Wir gehen zur Schule.', 'Gegenwart', 'Präsens'],
            ['Er hat ein Eis gegessen.', 'Vergangenheit', 'Perfekt'],
            ['Ich spielte gestern Fußball.', 'Vergangenheit', 'Präteritum'],
            ['Wir waren im Zoo.', 'Vergangenheit', 'Präteritum'],
            ['Sie ist nach Hause gegangen.', 'Vergangenheit', 'Perfekt'],
            ['Morgen werde ich schwimmen.', 'Zukunft', ''],
            ['Nächste Woche fahren wir weg.', 'Zukunft', ''],
            ['Er wird bald ankommen.', 'Zukunft', ''],
        ] as [$sentence, $when, $form]) {
            $fields = ['word' => $sentence, 'tense_when' => $when];
            if ($form !== '') {
                $fields['tense_form'] = $form;
            }
            $add('deutsch_zeitformen', 'all', $fields);
        }

        // ── SILBEN EASY ────────────────────────────────────────────────────────
        foreach ([
            ['Hund', 1], ['Baum', 1], ['Ball', 1], ['Tisch', 1], ['Buch', 1],
            ['Katze', 2], ['Sonne', 2], ['Blume', 2], ['Vogel', 2], ['Auto', 2],
        ] as [$w, $syl]) {
            $add('deutsch_silben', 'easy', ['word' => $w, 'syllables' => $syl]);
        }

        // ── SILBEN MEDIUM ──────────────────────────────────────────────────────
        foreach ([
            ['Fenster', 2], ['Blumen', 2], ['Wasser', 2], ['Fahrrad', 2], ['Kinder', 2], ['Garten', 2], ['Zeitung', 2],
            ['Banane', 3], ['Elefant', 3], ['Schmetterling', 3],
        ] as [$w, $syl]) {
            $add('deutsch_silben', 'medium', ['word' => $w, 'syllables' => $syl]);
        }

        // ── SILBEN HARD ────────────────────────────────────────────────────────
        foreach ([
            ['Regenschirm', 3], ['Apfelbaum', 3], ['Eisenbahn', 3], ['Straßenbahn', 3], ['Feuerwehr', 3],
            ['Sonnenblume', 4], ['Schokolade', 4], ['Fußballspieler', 4], ['Kindergarten', 4],
            ['Wassermelone', 5],
        ] as [$w, $syl]) {
            $add('deutsch_silben', 'hard', ['word' => $w, 'syllables' => $syl]);
        }

        // ── SATZZEICHEN EASY ───────────────────────────────────────────────────
        foreach ([
            ['Ich gehe nach Hause', '.'],
            ['Kommst du mit', '?'],
            ['Pass auf', '!'],
            ['Die Sonne scheint', '.'],
            ['Wie heißt du', '?'],
            ['Lauf schnell', '!'],
            ['Der Hund bellt', '.'],
            ['Hast du Hunger', '?'],
        ] as [$text, $mark]) {
            $add('deutsch_satzzeichen', 'easy', ['word' => $text, 'punctuation_mark' => $mark]);
        }

        // ── SATZZEICHEN MEDIUM ─────────────────────────────────────────────────
        foreach ([
            ['Ich habe heute viel gelernt', '.'],
            ['Warum weinst du', '?'],
            ['Halt sofort an', '!'],
            ['Wir gehen morgen ins Kino', '.'],
            ['Wo ist mein Buch', '?'],
            ['Sei bitte leise', '!'],
            ['Meine Schwester spielt Klavier', '.'],
            ['Kannst du mir helfen', '?'],
        ] as [$text, $mark]) {
            $add('deutsch_satzzeichen', 'medium', ['word' => $text, 'punctuation_mark' => $mark]);
        }

        return $rows;
    }
}
