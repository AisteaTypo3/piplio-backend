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
        $this->addOption('pid', null, InputOption::VALUE_REQUIRED, 'Page ID to assign records to', 1);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connection = $this->connectionPool->getConnectionForTable('tx_pipliobackend_word');
        $pid        = (int)$input->getOption('pid');
        $force      = (bool)$input->getOption('force');

        $existing = $connection->count('*', 'tx_pipliobackend_word', ['deleted' => 0]);
        if ($existing > 0 && !$force) {
            $output->writeln("<info>Already {$existing} words in database. Use --force to re-seed.</info>");
            return Command::SUCCESS;
        }

        if ($force) {
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

        return $rows;
    }
}
