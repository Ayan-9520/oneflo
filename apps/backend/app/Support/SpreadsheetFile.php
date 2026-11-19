<?php

declare(strict_types=1);

namespace App\Support;

use ZipArchive;

final class SpreadsheetFile
{
    /**
     * @param  list<string>  $headers
     * @param  list<list<string|null>>  $rows
     */
    public static function binary(array $headers, array $rows): string
    {
        $strings = [];
        $index = [];
        $remember = function (?string $value) use (&$strings, &$index): int {
            $value ??= '';
            if (! array_key_exists($value, $index)) {
                $index[$value] = count($strings);
                $strings[] = $value;
            }

            return $index[$value];
        };

        $sheetRows = '';
        $line = 1;
        $sheetRows .= self::rowXml($line, array_map(fn (string $header) => $remember($header), $headers));
        foreach ($rows as $row) {
            $line++;
            $sheetRows .= self::rowXml($line, array_map(fn ($value) => $remember($value === null ? '' : (string) $value), $row));
        }

        $shared = '';
        foreach ($strings as $string) {
            $shared .= '<si><t>'.self::escape($string).'</t></si>';
        }

        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Employees" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/></Relationships>',
            'xl/sharedStrings.xml' => '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($strings).'" uniqueCount="'.count($strings).'">'.$shared.'</sst>',
            'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheetRows.'</sheetData></worksheet>',
        ];

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        foreach ($files as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();
        $binary = (string) file_get_contents($path);
        unlink($path);

        return $binary;
    }

    /**
     * @return list<array<string, string>>
     */
    public static function rows(string $binary): array
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $binary);
        $zip = new ZipArchive;
        $zip->open($path);
        $shared = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if (is_string($sharedXml)) {
            $xml = simplexml_load_string($sharedXml);
            foreach ($xml->si ?? [] as $item) {
                $shared[] = (string) $item->t;
            }
        }
        $sheet = simplexml_load_string((string) $zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();
        unlink($path);

        $matrix = [];
        foreach ($sheet->sheetData->row ?? [] as $row) {
            $values = [];
            foreach ($row->c ?? [] as $cell) {
                $values[] = isset($cell['t']) && (string) $cell['t'] === 's'
                    ? ($shared[(int) $cell->v] ?? '')
                    : (string) $cell->v;
            }
            $matrix[] = $values;
        }

        if ($matrix === []) {
            return [];
        }

        $headers = array_map(fn (string $header) => strtolower(trim($header)), $matrix[0]);
        $records = [];
        foreach (array_slice($matrix, 1) as $row) {
            $record = [];
            foreach ($headers as $position => $header) {
                if ($header !== '') {
                    $record[$header] = $row[$position] ?? '';
                }
            }
            $records[] = $record;
        }

        return $records;
    }

    /**
     * @param  list<int>  $cells
     */
    private static function rowXml(int $line, array $cells): string
    {
        $xml = '<row r="'.$line.'">';
        foreach ($cells as $position => $sharedIndex) {
            $column = chr(65 + $position);
            $xml .= '<c r="'.$column.$line.'" t="s"><v>'.$sharedIndex.'</v></c>';
        }
        $xml .= '</row>';

        return $xml;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
