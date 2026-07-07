<?php

declare(strict_types=1);

use Aistea\PiplioBackend\Command\SeedWordsCommand;
use Aistea\PiplioBackend\Utility\WordTopics;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use TYPO3\CMS\Core\Database\ConnectionPool;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$outputDir = dirname(__DIR__) . '/outputs/piplio-import';
if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    throw new RuntimeException('Could not create output directory: ' . $outputDir);
}

$outputFile = $outputDir . '/Piplio-Word-Import-Live.xlsx';

$reflection = new ReflectionClass(SeedWordsCommand::class);
$instance = $reflection->newInstanceWithoutConstructor();
$buildRows = $reflection->getMethod('buildRows');
$buildRows->setAccessible(true);

/** @var list<array<string, mixed>> $seedRows */
$seedRows = $buildRows->invoke($instance, 1, time());

$headers = WordTopics::IMPORT_COLUMNS;
$importRows = [];
$topicCounts = [];
$difficultyCounts = [];

foreach ($seedRows as $row) {
    $topic = (string)($row['topic'] ?? '');
    $difficulty = (string)($row['difficulty'] ?? '');
    $topicCounts[$topic] = ($topicCounts[$topic] ?? 0) + 1;
    $difficultyCounts[$difficulty] = ($difficultyCounts[$difficulty] ?? 0) + 1;

    $line = [];
    foreach ($headers as $header) {
        $value = $row[$header] ?? '';
        $line[] = is_scalar($value) ? (string)$value : '';
    }
    $importRows[] = $line;
}

ksort($topicCounts);
ksort($difficultyCounts);

$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0);

$importSheet = $spreadsheet->createSheet();
$importSheet->setTitle('Import');
$summarySheet = $spreadsheet->createSheet();
$summarySheet->setTitle('Uebersicht');
$notesSheet = $spreadsheet->createSheet();
$notesSheet->setTitle('Hinweise');

$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '0071E3'],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'D2D2D7'],
        ],
    ],
];

$importSheet->fromArray($headers, null, 'A1');
$importSheet->fromArray($importRows, null, 'A2');

$lastColumnIndex = count($headers) - 1;
$lastColumn = chr(ord('A') + $lastColumnIndex);
$lastDataRow = count($importRows) + 1;

$importSheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray($headerStyle);
$importSheet->freezePane('A2');
$importSheet->setAutoFilter('A1:' . $lastColumn . $lastDataRow);
$importSheet->getRowDimension(1)->setRowHeight(24);

$columnWidths = [
    'A' => 24, 'B' => 14, 'C' => 30, 'D' => 12, 'E' => 18, 'F' => 12, 'G' => 22,
    'H' => 28, 'I' => 28, 'J' => 28, 'K' => 18, 'L' => 34, 'M' => 18, 'N' => 18,
    'O' => 14, 'P' => 16, 'Q' => 10,
];
foreach ($columnWidths as $column => $width) {
    $importSheet->getColumnDimension($column)->setWidth($width);
}

$importSheet->getStyle('A2:' . $lastColumn . $lastDataRow)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
$importSheet->getStyle('A2:' . $lastColumn . $lastDataRow)->getAlignment()->setWrapText(true);

$topicList = '"' . implode(',', WordTopics::ALLOWED_TOPICS) . '"';
$difficultyList = '"' . implode(',', WordTopics::ALLOWED_DIFFICULTIES) . '"';
$boolList = '"0,1,true,false,ja,nein,yes,no"';

for ($rowNumber = 2; $rowNumber <= max(400, $lastDataRow + 20); $rowNumber++) {
    $topicValidation = $importSheet->getCell('A' . $rowNumber)->getDataValidation();
    $topicValidation->setType(DataValidation::TYPE_LIST);
    $topicValidation->setErrorStyle(DataValidation::STYLE_STOP);
    $topicValidation->setAllowBlank(false);
    $topicValidation->setShowDropDown(true);
    $topicValidation->setFormula1($topicList);

    $difficultyValidation = $importSheet->getCell('B' . $rowNumber)->getDataValidation();
    $difficultyValidation->setType(DataValidation::TYPE_LIST);
    $difficultyValidation->setErrorStyle(DataValidation::STYLE_STOP);
    $difficultyValidation->setAllowBlank(false);
    $difficultyValidation->setShowDropDown(true);
    $difficultyValidation->setFormula1($difficultyList);

    $isNomenValidation = $importSheet->getCell('F' . $rowNumber)->getDataValidation();
    $isNomenValidation->setType(DataValidation::TYPE_LIST);
    $isNomenValidation->setErrorStyle(DataValidation::STYLE_INFORMATION);
    $isNomenValidation->setAllowBlank(true);
    $isNomenValidation->setShowDropDown(true);
    $isNomenValidation->setFormula1($boolList);

    $hiddenValidation = $importSheet->getCell('Q' . $rowNumber)->getDataValidation();
    $hiddenValidation->setType(DataValidation::TYPE_LIST);
    $hiddenValidation->setErrorStyle(DataValidation::STYLE_INFORMATION);
    $hiddenValidation->setAllowBlank(true);
    $hiddenValidation->setShowDropDown(true);
    $hiddenValidation->setFormula1($boolList);
}

$summaryRows = [
    ['Piplio Live Import Workbook'],
    ['Erstellt aus dem aktuellen Seed-Datensatz der Extension.'],
    [''],
    ['Gesamtanzahl Datensaetze', (string)count($importRows)],
    ['Anzahl Themen', (string)count($topicCounts)],
    ['Anzahl Schwierigkeits-Pools', (string)count($difficultyCounts)],
    [''],
    ['Datensaetze pro Thema', ''],
];

foreach ($topicCounts as $topic => $count) {
    $summaryRows[] = [$topic, (string)$count];
}

$summaryRows[] = [''];
$summaryRows[] = ['Datensaetze pro Difficulty', ''];

foreach ($difficultyCounts as $difficulty => $count) {
    $summaryRows[] = [$difficulty, (string)$count];
}

$summarySheet->fromArray($summaryRows, null, 'A1');
$summarySheet->mergeCells('A1:B1');
$summarySheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$summarySheet->getStyle('A4:B6')->getFont()->setBold(true);
$summarySheet->getStyle('A8:B8')->getFont()->setBold(true);
$summarySheet->getColumnDimension('A')->setWidth(32);
$summarySheet->getColumnDimension('B')->setWidth(18);

$notesRows = [
    ['Live-Import Hinweise'],
    ['Diese Datei kann direkt ueber das Backend-Modul der Extension importiert werden.'],
    [''],
    ['Empfohlener Modus fuer den ersten Live-Import', 'append oder upsert, nicht replace'],
    ['Warum nicht replace?', 'replace loescht fuer alle in der Datei enthaltenen Themen zuerst bestehende Datensaetze.'],
    [''],
    ['Wichtige Felder'],
    ['topic', 'Muss eines der erlaubten Themen sein.'],
    ['difficulty', 'easy, medium, hard oder all (nur bei Zeitformen).'],
    ['word', 'Das eigentliche Aufgabenfeld; je nach Thema unterschiedlich genutzt.'],
    ['hidden', '0 fuer sichtbar, 1 fuer ausgeblendet.'],
    [''],
    ['Themenspezifische Zusatzfelder'],
    ['deutsch_artikel', 'artikel'],
    ['deutsch_reime', 'rhyme_words, no_rhyme_words'],
    ['deutsch_gross_klein', 'is_nomen'],
    ['deutsch_wortarten', 'word_type'],
    ['deutsch_plural', 'plural_form, wrong_options'],
    ['deutsch_rechtschreibung', 'correct, wrong_options, full_sentence'],
    ['deutsch_zeitformen', 'tense_when, tense_form'],
    ['deutsch_silben', 'syllables'],
    ['deutsch_satzzeichen', 'punctuation_mark'],
];

$notesSheet->fromArray($notesRows, null, 'A1');
$notesSheet->mergeCells('A1:B1');
$notesSheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$notesSheet->getColumnDimension('A')->setWidth(34);
$notesSheet->getColumnDimension('B')->setWidth(96);
$notesSheet->getStyle('A1:B40')->getAlignment()->setWrapText(true);

$spreadsheet->setActiveSheetIndex(0);

$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
$writer->save($outputFile);

echo $outputFile . PHP_EOL;
