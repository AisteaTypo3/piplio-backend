<?php

declare(strict_types=1);

namespace Aistea\PiplioBackend\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;

#[AsCommand(name: 'piplio:seed-topics', description: 'Seed initial package/topic/grade-recommendation data for the Piplio app')]
class SeedTopicsCommand extends Command
{
    public function __construct(private readonly ConnectionPool $connectionPool)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Re-seed even if data already exists');
        $this->addOption('truncate', 't', InputOption::VALUE_NONE, 'Truncate the tables before re-seeding. Only valid together with --force');
        $this->addOption('pid', null, InputOption::VALUE_REQUIRED, 'Page ID to assign records to', 1);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $packageConnection = $this->connectionPool->getConnectionForTable('tx_pipliobackend_package');
        $topicConnection = $this->connectionPool->getConnectionForTable('tx_pipliobackend_topic');
        $gradeRecConnection = $this->connectionPool->getConnectionForTable('tx_pipliobackend_graderecommendation');

        $pid = (int)$input->getOption('pid');
        $force = (bool)$input->getOption('force');
        $truncate = (bool)$input->getOption('truncate');

        if ($truncate && !$force) {
            $output->writeln('<error>Use --truncate only together with --force.</error>');
            return Command::INVALID;
        }

        $existing = $packageConnection->count('*', 'tx_pipliobackend_package', ['deleted' => 0]);
        if ($existing > 0 && !$force) {
            $output->writeln("<info>Already {$existing} packages in database. Use --force to re-seed.</info>");
            return Command::SUCCESS;
        }

        if ($force) {
            if (!$truncate) {
                $output->writeln('<error>Refusing to replace existing data without --truncate. Use --force --truncate for destructive reseeding.</error>');
                return Command::INVALID;
            }
            $gradeRecConnection->truncate('tx_pipliobackend_graderecommendation');
            $topicConnection->truncate('tx_pipliobackend_topic');
            $packageConnection->truncate('tx_pipliobackend_package');
            $output->writeln('<comment>Tables truncated.</comment>');
        }

        $now = time();
        $base = ['pid' => $pid, 'hidden' => 0, 'deleted' => 0, 'crdate' => $now, 'tstamp' => $now];

        $packageUidByPackageId = [];
        foreach ($this->packages() as [$packageId, $title, $description, $recommendedGrade]) {
            $packageConnection->insert('tx_pipliobackend_package', array_merge($base, [
                'package_id' => $packageId,
                'title' => $title,
                'description' => $description,
                'recommended_grade' => $recommendedGrade,
            ]));
            $packageUidByPackageId[$packageId] = (int)$packageConnection->lastInsertId();
        }

        $topicCount = 0;
        foreach ($this->topics() as [$topicId, $title, $subtitle, $colorKey, $order, $packageId]) {
            $topicConnection->insert('tx_pipliobackend_topic', array_merge($base, [
                'topic_id' => $topicId,
                'title' => $title,
                'subtitle' => $subtitle,
                'color_key' => $colorKey,
                'sort_order' => $order,
                'package' => $packageUidByPackageId[$packageId],
            ]));
            $topicCount++;
        }

        $gradeRecCount = 0;
        foreach ($this->gradeRecommendations() as $grade => $packageIds) {
            foreach ($packageIds as $sorting => $packageId) {
                $gradeRecConnection->insert('tx_pipliobackend_graderecommendation', array_merge($base, [
                    'grade' => $grade,
                    'package' => $packageUidByPackageId[$packageId],
                    'sorting' => $sorting,
                ]));
                $gradeRecCount++;
            }
        }

        $output->writeln(sprintf(
            '<info>✓ Seeded %d packages, %d topics and %d grade recommendations successfully.</info>',
            count($packageUidByPackageId),
            $topicCount,
            $gradeRecCount
        ));

        return Command::SUCCESS;
    }

    private function packages(): array
    {
        return [
            ['grade1_numbers20', 'Zahlenraum bis 20', 'Zaehlen, ordnen und Zahlen sicher erkennen.', '1'],
            ['grade1_arithmetic20', 'Rechnen bis 20', 'Plus und Minus im kleinen Zahlenraum.', '1'],
            ['grade1_numbers100', 'Zahlenraum bis 100', 'Zehner, Einer und erste groessere Zahlen.', '1'],
            ['grade2_arithmetic100', 'Rechnen bis 100', 'Vorbereitung fuer Plus und Minus im groesseren Zahlenraum.', '2'],
            ['grade3_multiplication_intro', 'Malnehmen Start', 'Einmaleins starten und erste Umkehraufgaben verstehen.', '3'],
            ['alltag_1', 'Alltagsmathe', 'Uhrzeit lesen und mit Geld rechnen.', '1'],
            ['deutsch_1', 'Deutsch Klasse 1', 'Artikel, Reime und richtig schreiben.', '1'],
            ['deutsch_23', 'Deutsch Klasse 2-3', 'Wortarten und Mehrzahl üben.', '2'],
        ];
    }

    private function topics(): array
    {
        return [
            ['numbers20', 'Zahlen bis 20', 'Zählen, ordnen & vergleichen', 'numbers20', 0, 'grade1_numbers20'],
            ['addition20', 'Addition bis 20', 'Plus rechnen', 'addition20', 1, 'grade1_arithmetic20'],
            ['subtraction20', 'Subtraktion bis 20', 'Minus rechnen', 'subtraction20', 2, 'grade1_arithmetic20'],
            ['numbers100', 'Zahlen bis 100', 'Zehner & Einer', 'numbers100', 3, 'grade1_numbers100'],
            ['addition100', 'Addition bis 100', 'Plus mit groesseren Zahlen', 'addition100', 4, 'grade2_arithmetic100'],
            ['subtraction100', 'Subtraktion bis 100', 'Minus im Zahlenraum 100', 'subtraction100', 5, 'grade2_arithmetic100'],
            ['make100', 'Ergänzen bis 100', 'Fehlende Zahl finden', 'make100', 6, 'grade2_arithmetic100'],
            ['bridge100', 'Zehnerübergang', 'Über den Zehner rechnen', 'bridge100', 7, 'grade2_arithmetic100'],
            ['times2_5_10', 'Einmaleins 2, 5, 10', 'Einfache Reihen sicher lernen', 'times2_5_10', 8, 'grade3_multiplication_intro'],
            ['times3_4', 'Einmaleins 3 und 4', 'Weitere Malreihen üben', 'times3_4', 9, 'grade3_multiplication_intro'],
            ['division_intro', 'Teilen am Anfang', 'Umkehraufgaben verstehen', 'division_intro', 10, 'grade3_multiplication_intro'],
            ['wall_math', 'Zahlenmauer', 'Finde den fehlenden Stein', 'wall_math', 11, 'grade1_arithmetic20'],
            ['clock', 'Uhrzeit', 'Wie viel Uhr ist es?', 'clock', 12, 'alltag_1'],
            ['money', 'Geld', 'Münzen zählen & Rückgeld', 'money', 13, 'alltag_1'],
            ['deutsch_artikel', 'Artikel', 'der, die oder das?', 'deutsch_artikel', 20, 'deutsch_1'],
            ['deutsch_reime', 'Reimwörter', 'Was klingt gleich?', 'deutsch_reime', 21, 'deutsch_1'],
            ['deutsch_gross_klein', 'Groß & Klein', 'Richtig schreiben', 'deutsch_gross_klein', 22, 'deutsch_1'],
            ['deutsch_wortarten', 'Wortarten', 'Nomen, Verb, Adjektiv', 'deutsch_wortarten', 23, 'deutsch_23'],
            ['deutsch_plural', 'Mehrzahl', 'Singular & Plural', 'deutsch_plural', 24, 'deutsch_23'],
        ];
    }

    private function gradeRecommendations(): array
    {
        return [
            '1' => ['grade1_numbers20', 'grade1_arithmetic20', 'grade1_numbers100', 'alltag_1', 'deutsch_1'],
            '2' => ['grade2_arithmetic100', 'alltag_1', 'deutsch_1', 'deutsch_23'],
            '3' => ['grade3_multiplication_intro', 'deutsch_23'],
        ];
    }
}
