<?php

declare(strict_types=1);

namespace App\Support;

use Dompdf\Dompdf;

final class LetterPdf
{
    public function binary(string $html): string
    {
        $pdf = new Dompdf(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
        $pdf->loadHtml($html);
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }
}
