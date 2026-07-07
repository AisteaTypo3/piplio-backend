<?php

declare(strict_types=1);

use Aistea\PiplioBackend\Utility\WordTopics;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$outputDir = dirname(__DIR__) . '/outputs/piplio-import';
if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    throw new RuntimeException('Could not create output directory: ' . $outputDir);
}

$outputFile = $outputDir . '/Piplio-Word-Import-Template.xlsx';

$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0);

$importSheet = $spreadsheet->createSheet();
$importSheet->setTitle('Import');
$notesSheet = $spreadsheet->createSheet();
$notesSheet->setTitle('Hinweise');

$headers = WordTopics::IMPORT_COLUMNS;
$importSheet->fromArray($headers, null, 'A1');

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

$examples = [
    ['deutsch_artikel', 'easy', 'Hund', 'der', '', '1', '', '', '', '', '', '', '', '', '', '', '0'],
    ['deutsch_reime', 'medium', 'Stern', '', '', '0', '', 'fern,Kern,gern', 'Ball,Maus,Hund,Baum', '', '', '', '', '', '', '', '0'],
    ['deutsch_gross_klein', 'hard', 'neugierig', '', '', '0', '', '', '', '', '', '', '', '', '', '', '0'],
    ['deutsch_wortarten', 'easy', 'laufen', '', 'Verb', '0', '', '', '', '', '', '', '', '', '', '', '0'],
    ['deutsch_plural', 'medium', 'Baum', '', '', '0', 'Baeume', '', '', 'Baums,Baume,Baumen', '', '', '', '', '', '', '0'],
    ['deutsch_rechtschreibung', 'medium', 'Der H__d spielt im Garten.', '', '', '0', '', '', '', 'Hand,Hemd,Hundt', 'Hund', 'Der Hund spielt im Garten.', '', '', '', '', '0'],
    ['deutsch_zeitformen', 'all', 'Morgen gehe ich in die Schule.', '', '', '0', '', '', '', '', '', '', 'Zukunft', 'Präsens', '', '', '0'],
    ['deutsch_silben', 'easy', 'Schmetterling', '', '', '0', '', '', '', '', '', '', '', '', '4', '', '0'],
    ['deutsch_satzzeichen', 'medium', 'Wie spaet ist es', '', '', '0', '', '', '', '', '', '', '', '', '', '?', '0'],
];

$importSheet->fromArray($examples, null, 'A2');

$lastColumn = chr(ord('A') + count($headers) - 1);
$importSheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray($headerStyle);
$importSheet->freezePane('A2');
$importSheet->setAutoFilter('A1:' . $lastColumn . '10');
$importSheet->getRowDimension(1)->setRowHeight(24);

$columnWidths = [
    'A' => 24, 'B' => 14, 'C' => 30, 'D' => 12, 'E' => 18, 'F' => 12, 'G' => 22,
    'H' => 28, 'I' => 28, 'J' => 28, 'K' => 18, 'L' => 34, 'M' => 18, 'N' => 18,
    'O' => 14, 'P' => 16, 'Q' => 10,
];
foreach ($columnWidths as $column => $width) {
    $importSheet->getColumnDimension($column)->setWidth($width);
}

$importSheet->getStyle('A2:' . $lastColumn . '200')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
$importSheet->getStyle('A2:' . $lastColumn . '200')->getAlignment()->setWrapText(true);

$topicList = '"' . implode(',', WordTopics::ALLOWED_TOPICS) . '"';
$difficultyList = '"' . implode(',', WordTopics::ALLOWED_DIFFICULTIES) . '"';
$hiddenList = '"0,1,true,false,ja,nein,yes,no"';

for ($row = 2; $row <= 200; $row++) {
    $topicValidation = $importSheet->getCell('A' . $row)->getDataValidation();
    $topicValidation->setType(DataValidation::TYPE_LIST);
    $topicValidation->setErrorStyle(DataValidation::STYLE_STOP);
    $topicValidation->setAllowBlank(false);
    $topicValidation->setShowDropDown(true);
    $topicValidation->setFormula1($topicList);

    $difficultyValidation = $importSheet->getCell('B' . $row)->getDataValidation();
    $difficultyValidation->setType(DataValidation::TYPE_LIST);
    $difficultyValidation->setErrorStyle(DataValidation::STYLE_STOP);
    $difficultyValidation->setAllowBlank(false);
    $difficultyValidation->setShowDropDown(true);
    $difficultyValidation->setFormula1($difficultyList);

    $boolValidation = $importSheet->getCell('F' . $row)->getDataValidation();
    $boolValidation->setType(DataValidation::TYPE_LIST);
    $boolValidation->setErrorStyle(DataValidation::STYLE_INFORMATION);
    $boolValidation->setAllowBlank(true);
    $boolValidation->setShowDropDown(true);
    $boolValidation->setFormula1($hiddenList);

    $hiddenValidation = $importSheet->getCell('Q' . $row)->getDataValidation();
    $hiddenValidation->setType(DataValidation::TYPE_LIST);
    $hiddenValidation->setErrorStyle(DataValidation::STYLE_INFORMATION);
    $hiddenValidation->setAllowBlank(true);
    $hiddenValidation->setShowDropDown(true);
    $hiddenValidation->setFormula1($hiddenList);
}

$importSheet->setSelectedCell('A2');

$notes = [
    ['Piplio Word Import Template'],
    ['Diese Datei passt direkt zur Backend-Importfunktion der Extension.'],
    [''],
    ['Import-Modi'],
    ['append', 'Nur neue Datensaetze werden angelegt. Bereits vorhandene Kombinationen aus topic + difficulty + word werden uebersprungen.'],
    ['upsert', 'Bestehende Datensaetze werden aktualisiert, neue werden angelegt.'],
    ['replace', 'Alle vorhandenen Datensaetze der in der Datei enthaltenen Themen werden zuerst geloescht und danach aus der Datei neu aufgebaut.'],
    [''],
    ['Pflichtspalten'],
    ['topic', 'Muss eines der erlaubten Themen sein.'],
    ['difficulty', 'Erlaubt: easy, medium, hard, all.'],
    ['word', 'Der Kerninhalt des Datensatzes; je nach Thema unterschiedlich genutzt.'],
    [''],
    ['Themen und relevante Zusatzspalten'],
    ['deutsch_artikel', 'artikel'],
    ['deutsch_reime', 'rhyme_words, no_rhyme_words'],
    ['deutsch_gross_klein', 'is_nomen'],
    ['deutsch_wortarten', 'word_type'],
    ['deutsch_plural', 'plural_form, wrong_options'],
    ['deutsch_rechtschreibung', 'wrong_options, correct, full_sentence'],
    ['deutsch_zeitformen', 'tense_when, tense_form'],
    ['deutsch_silben', 'syllables'],
    ['deutsch_satzzeichen', 'punctuation_mark'],
    [''],
    ['Hinweise'],
    ['Bool-Felder', 'Fuer is_nomen und hidden funktionieren z.B. 1, true, ja, yes; alles andere wird als 0 behandelt.'],
    ['Leere Felder', 'Themenspezifische Felder, die fuer einen Datensatz nicht gebraucht werden, koennen leer bleiben.'],
    ['PID', 'Die Ziel-PID wird im Backend beim Import gesetzt und muss nicht in der Datei stehen.'],
];

$notesSheet->fromArray($notes, null, 'A1');
$notesSheet->mergeCells('A1:B1');
$notesSheet->getStyle('A1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '1D1D1F']],
]);
$notesSheet->getStyle('A4:B7')->getFont()->setBold(true);
$notesSheet->getStyle('A9:B12')->getFont()->setBold(true);
$notesSheet->getStyle('A14:B22')->getFont()->setBold(true);
$notesSheet->getStyle('A25:B27')->getFont()->setBold(true);
$notesSheet->getColumnDimension('A')->setWidth(24);
$notesSheet->getColumnDimension('B')->setWidth(100);
$notesSheet->getStyle('A1:B40')->getAlignment()->setWrapText(true);
$notesSheet->freezePane('A2');

$spreadsheet->setActiveSheetIndex(0);

$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
$writer->save($outputFile);

echo $outputFile . PHP_EOL;
