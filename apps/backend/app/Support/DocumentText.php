<?php

declare(strict_types=1);

namespace App\Support;

final class DocumentText
{
    /**
     * @return list<string>
     */
    public static function lines(string $html): array
    {
        $withoutStyle = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html) ?? $html;
        $withoutScript = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $withoutStyle) ?? $withoutStyle;
        $withBreaks = preg_replace('/<(br|\/p|\/div|\/h1|\/tr|\/li)\b[^>]*>/i', "\n", $withoutScript) ?? $withoutScript;
        $text = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\r", '', $text);
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $rows = [];
        foreach (preg_split("/\n/", $text) ?: [] as $row) {
            $row = trim($row);
            if ($row === '' || preg_match('/:\s*$/', $row) === 1 || str_contains($row, '{')) {
                continue;
            }
            $rows[] = $row;
        }

        return $rows;
    }
}
