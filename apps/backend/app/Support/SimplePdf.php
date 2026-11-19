<?php

declare(strict_types=1);

namespace App\Support;

final class SimplePdf
{
    /**
     * @param  list<string>  $lines
     */
    public static function text(array $lines): string
    {
        $content = "BT /F1 11 Tf 48 800 Td 16 TL\n";
        foreach ($lines as $line) {
            foreach (self::wrap($line) as $row) {
                $content .= '(' . self::escape($row) . ") '\n";
            }
        }
        $content .= "ET\n";
        $objects = [
            "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n",
            "2 0 obj << /Type /Pages /Count 1 /Kids [3 0 R] >> endobj\n",
            "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj\n",
            '4 0 obj << /Length ' . strlen($content) . " >> stream\n" . $content . "endstream\nendobj\n",
            "5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($index = 1; $index <= count($objects); $index++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$index]);
        }
        $pdf .= "trailer << /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    private static function escape(string $line): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $line) ?: $line;

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $ascii);
    }
}
