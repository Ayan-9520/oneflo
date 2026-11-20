<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Minimal first-sheet reader and writer for XLSX and CSV.
 * Formulas are never evaluated: only cached cell values are read.
 */
final class Spreadsheet
{
    private const MAIN_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const MAX_UNCOMPRESSED_BYTES = 40 * 1024 * 1024;

    /**
     * @return array<int, list<string>> Rows keyed by 1-based sheet row number.
     */
    /**
     * @param  list<string>  $headerHints  Normalised header names; the sheet whose first row matches most of them is read.
     */
    public static function read(string $path, string $extension, int $maxRows, array $headerHints = []): array
    {
        return match (strtolower($extension)) {
            'csv', 'txt' => self::readCsv($path, $maxRows),
            'xlsx' => self::readXlsx($path, $maxRows, $headerHints),
            default => throw new RuntimeException('Upload an .xlsx or .csv file.'),
        };
    }

    /**
     * @param  list<list<string>>  $rows
     */
    public static function xlsx(array $rows, string $sheetName = 'Candidates'): string
    {
        $sheetRows = '';
        foreach (array_values($rows) as $index => $cells) {
            $number = $index + 1;
            $sheetRows .= '<row r="'.$number.'">';
            foreach (array_values($cells) as $column => $value) {
                $ref = self::columnName($column).$number;
                $style = $index === 0 ? ' s="1"' : '';
                $sheetRows .= '<c r="'.$ref.'" t="inlineStr"'.$style.'><is><t xml:space="preserve">'.self::xml($value).'</t></is></c>';
            }
            $sheetRows .= '</row>';
        }
        $columns = max(1, ...array_map('count', $rows ?: [[]]));
        $cols = '<cols><col min="1" max="'.$columns.'" width="24" customWidth="1"/></cols>';

        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="'.self::MAIN_NS.'" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.self::xml($sheetName).'" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="'.self::MAIN_NS.'"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="2"><xf fontId="0"/><xf fontId="1" applyFont="1"/></cellXfs></styleSheet>',
            'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="'.self::MAIN_NS.'"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'.$cols.'<sheetData>'.$sheetRows.'</sheetData></worksheet>',
        ];

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        if ($path === false || $zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The workbook could not be created.');
        }
        foreach ($files as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();
        $binary = (string) file_get_contents($path);
        @unlink($path);

        return $binary;
    }

    /**
     * Neutralises spreadsheet formula injection when values are written back out.
     */
    public static function safeCell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }

    /**
     * @return array<int, list<string>>
     */
    private static function readCsv(string $path, int $maxRows): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('The file could not be read.');
        }
        $first = (string) fgets($handle);
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        rewind($handle);
        $rows = [];
        $number = 0;
        while (($cells = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $number++;
            if ($number === 1 && isset($cells[0])) {
                $cells[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $cells[0]) ?? '';
            }
            $rows[$number] = array_map(fn ($cell) => trim((string) $cell), $cells);
            if (count($rows) > $maxRows + 1) {
                break;
            }
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @return array<int, list<string>>
     */
    /**
     * @param  list<string>  $headerHints
     */
    private static function readXlsx(string $path, int $maxRows, array $headerHints): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('The file is not a valid .xlsx workbook.');
        }
        try {
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $total += (int) ($zip->statIndex($i)['size'] ?? 0);
            }
            if ($total > self::MAX_UNCOMPRESSED_BYTES) {
                throw new RuntimeException('The workbook is too large to import.');
            }
            $sheetXmls = [];
            foreach (self::sheetPaths($zip) as $sheetPath) {
                $xml = $zip->getFromName($sheetPath);
                if ($xml !== false) {
                    $sheetXmls[] = $xml;
                }
            }
            if ($sheetXmls === []) {
                throw new RuntimeException('The workbook has no worksheet.');
            }
            $shared = self::sharedStrings($zip->getFromName('xl/sharedStrings.xml') ?: null);
        } finally {
            $zip->close();
        }

        $best = [];
        $bestScore = -1;
        foreach ($sheetXmls as $xml) {
            $rows = self::sheetRows(self::parseXml($xml), $shared, $maxRows);
            $header = $rows === [] ? [] : reset($rows);
            $keys = array_map(fn ($cell) => preg_replace('/[^a-z0-9]/', '', strtolower($cell)) ?? '', $header);
            $score = count(array_intersect($keys, $headerHints));
            if ($score > $bestScore) {
                [$best, $bestScore] = [$rows, $score];
            }
        }

        return $best;
    }

    /**
     * @param  list<string>  $shared
     * @return array<int, list<string>>
     */
    private static function sheetRows(\SimpleXMLElement $sheet, array $shared, int $maxRows): array
    {
        $rows = [];
        foreach ($sheet->sheetData->row ?? [] as $row) {
            $number = (int) self::attr($row, 'r');
            $cells = [];
            $position = 0;
            foreach ($row->c as $cell) {
                $ref = self::attr($cell, 'r');
                $column = $ref !== '' ? self::columnIndex($ref) : $position;
                $position = $column + 1;
                $cells[$column] = self::cellValue($cell, $shared);
            }
            if ($cells === []) {
                continue;
            }
            $width = max(array_keys($cells)) + 1;
            $dense = [];
            for ($c = 0; $c < $width; $c++) {
                $dense[] = trim($cells[$c] ?? '');
            }
            $rows[$number ?: count($rows) + 1] = $dense;
            if (count($rows) > $maxRows + 1) {
                break;
            }
        }
        ksort($rows);

        return $rows;
    }

    /**
     * @return list<string>
     */
    private static function sheetPaths(ZipArchive $zip): array
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        $paths = [];
        if ($workbook !== false && $rels !== false) {
            $targets = [];
            foreach (self::parseXml($rels, 'http://schemas.openxmlformats.org/package/2006/relationships')->Relationship as $rel) {
                $target = ltrim(self::attr($rel, 'Target'), '/');
                $targets[self::attr($rel, 'Id')] = str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
            }
            foreach (self::parseXml($workbook)->sheets->sheet ?? [] as $sheet) {
                $id = (string) ($sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] ?? '');
                if (isset($targets[$id])) {
                    $paths[] = $targets[$id];
                }
            }
        }
        if ($paths === [] && $zip->locateName('xl/worksheets/sheet1.xml') !== false) {
            $paths[] = 'xl/worksheets/sheet1.xml';
        }

        return $paths;
    }

    /**
     * @return list<string>
     */
    private static function sharedStrings(?string $xml): array
    {
        if ($xml === null) {
            return [];
        }
        $strings = [];
        foreach (self::parseXml($xml)->si as $item) {
            $strings[] = self::richText($item);
        }

        return $strings;
    }

    /**
     * @param  list<string>  $shared
     */
    private static function cellValue(\SimpleXMLElement $cell, array $shared): string
    {
        $type = self::attr($cell, 't');
        if ($type === 'inlineStr') {
            return self::richText($cell->is);
        }
        $value = (string) ($cell->v ?? '');
        if ($type === 's') {
            return $shared[(int) $value] ?? '';
        }
        if ($type === 'b') {
            return $value === '1' ? 'TRUE' : 'FALSE';
        }
        if ($type === '' || $type === 'n') {
            if (preg_match('/^-?\d+(\.\d+)?[eE][+-]?\d+$/', $value) === 1) {
                return sprintf('%.0f', (float) $value);
            }
            if (preg_match('/^-?\d+\.0+$/', $value) === 1) {
                return (string) (int) $value;
            }
        }

        return $value;
    }

    private static function attr(\SimpleXMLElement $node, string $name): string
    {
        return (string) ($node->attributes()[$name] ?? '');
    }

    private static function richText(?\SimpleXMLElement $node): string
    {
        if ($node === null) {
            return '';
        }
        if (isset($node->t)) {
            return (string) $node->t;
        }
        $text = '';
        foreach ($node->r as $run) {
            $text .= (string) $run->t;
        }

        return $text;
    }

    private static function parseXml(string $xml, string $namespace = self::MAIN_NS): \SimpleXMLElement
    {
        if (preg_match('/<!DOCTYPE/i', $xml) === 1) {
            throw new RuntimeException('The workbook contains unsupported content.');
        }
        $previous = libxml_use_internal_errors(true);
        $node = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if ($node === false) {
            throw new RuntimeException('The workbook could not be read.');
        }

        return $node->children($namespace);
    }

    private static function columnIndex(string $ref): int
    {
        $letters = strtoupper((string) preg_replace('/\d+/', '', $ref));
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }

    private static function columnName(int $index): string
    {
        $name = '';
        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $name = chr(65 + (($n - 1) % 26)).$name;
        }

        return $name;
    }

    private static function xml(string $value): string
    {
        $clean = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value) ?? '';

        return htmlspecialchars($clean, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
