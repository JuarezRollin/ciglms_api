<?php

namespace App\Services;

use App\Models\GlTransmittal;
use Barryvdh\DomPDF\PDF;

class GlTransmittalPdfService
{
    public function render(GlTransmittal $transmittal): string
    {
        $transmittal->load(['category', 'creator', 'gls.client', 'gls.beneficiary', 'gls.serviceProvider']);

        $data = [
            'transmittal' => $transmittal,
            'logo' => base64_encode(file_get_contents(public_path('dswd_header.png'))),
            'bagongPilipinasLogo' => base64_encode(file_get_contents(public_path('bplogo.png'))),
        ];

        foreach ([false, true] as $compact) {
            /** @var PDF $pdf */
            $pdf = app('dompdf.wrapper');
            $pdf->loadView('printing.gl_transmittal', [...$data, 'compact' => $compact])
                ->setPaper('a4', 'landscape');
            $pdf->render();

            if ($pdf->getDomPDF()->getCanvas()->get_page_count() === 1) {
                return $pdf->output();
            }
        }

        throw new \RuntimeException('The GL transmittal does not fit on one page.');
    }
}
