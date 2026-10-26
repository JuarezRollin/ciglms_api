<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\GuaranteeLetter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class VoucherPdfService
{
    public function render(Batch $batch): string
    {
        return Pdf::loadView('printing.voucher_ors', $this->orsData($batch))->setPaper('a4')->output();
    }

    public function renderDv(Batch $batch, string $date): string
    {
        return Pdf::loadView('printing.voucher_dv', $this->dvData($batch, $date))->setPaper('a4')->output();
    }

    public function renderCombined(Batch $batch, string $date): string
    {
        return Pdf::loadView('printing.voucher_combined', [
            'ors' => $this->orsData($batch),
            'dv' => $this->dvData($batch, $date),
        ])->setPaper('a4')->output();
    }

    public function renderTransmittal(Batch $batch): string
    {
        $firstLetter = $this->firstLetter($batch);
        abort_if($batch->gls->count() > 10, 409, 'A voucher transmittal can contain at most 10 guarantee letters.');
        $batch->loadMissing(['creator', 'reviewer']);
        $provider = $firstLetter->branch?->serviceProvider;

        return Pdf::loadView('printing.voucher_transmittal', [
            'logo' => base64_encode(file_get_contents(public_path('dswd_header.png'))),
            'bagongPilipinasLogo' => base64_encode(file_get_contents(public_path('bplogo.png'))),
            'providerName' => Str::upper($provider?->name ?? ''),
            'providerAddress' => $firstLetter->branch?->address ?: ($provider?->address ?? ''),
            'voucherNumber' => $batch->voucher->voucher_no,
            'preparedBy' => Str::upper($batch->creator?->name ?? ''),
            'preparedByPosition' => $batch->creator?->position ?? '',
            'reviewedBy' => Str::upper($batch->reviewer?->name ?? ''),
            'reviewedByPosition' => $batch->reviewer?->position ?? '',
            'letters' => $batch->gls,
            'totalAmount' => 'Php '.number_format((float) $batch->total_amount, 2),
        ])->setPaper('a4', 'landscape')->output();
    }

    /** @return array<string, mixed> */
    private function orsData(Batch $batch): array
    {
        $firstLetter = $this->firstLetter($batch);
        $provider = $firstLetter->branch?->serviceProvider;

        return [
            'pageImages' => [
                1 => base64_encode(file_get_contents(resource_path('views/printing/voucher_ors_page_1.png'))),
                2 => base64_encode(file_get_contents(resource_path('views/printing/voucher_ors_page_2.png'))),
            ],
            'payee' => Str::upper($provider?->name ?? ''),
            'address' => $firstLetter->branch?->address ?: ($provider?->address ?? ''),
            'particulars' => $this->particulars($firstLetter),
            'fundSource' => $firstLetter->fundSource?->name ?? '',
            'amount' => 'Php '.number_format((float) $batch->total_amount, 2),
            'voucherNumber' => $batch->voucher->voucher_no,
        ];
    }

    /** @return array<string, mixed> */
    private function dvData(Batch $batch, string $date): array
    {
        $firstLetter = $this->firstLetter($batch);
        $provider = $firstLetter->branch?->serviceProvider;

        return [
            'pageImages' => [
                1 => base64_encode(file_get_contents(resource_path('views/printing/voucher_dv_page1.png'))),
                2 => base64_encode(file_get_contents(resource_path('views/printing/voucher_dv_page2.png'))),
                3 => base64_encode(file_get_contents(resource_path('views/printing/voucher_dv_page3.png'))),
            ],
            'date' => $date,
            'dvNumber' => $batch->dv?->dv_no ?? '',
            'payee' => Str::upper($provider?->name ?? ''),
            'address' => $provider?->address ?? '',
            'particulars' => $this->particulars($firstLetter),
            'fundSource' => $firstLetter->fundSource?->name ?? '',
            'amount' => number_format((float) $batch->total_amount, 2),
            'voucherNumber' => $batch->voucher->voucher_no,
        ];
    }

    private function firstLetter(Batch $batch): GuaranteeLetter
    {
        $batch->loadMissing([
            'voucher',
            'dv',
            'gls.client',
            'gls.beneficiary',
            'gls.assistanceType',
            'gls.relationship',
            'gls.branch.serviceProvider',
            'gls.fundSource',
        ]);

        $firstLetter = $batch->gls->first();
        abort_if($batch->voucher === null || $firstLetter === null, 409, 'This voucher batch cannot be printed.');

        return $firstLetter;
    }

    private function particulars(GuaranteeLetter $firstLetter): string
    {
        $client = $firstLetter->client;
        $possessivePronoun = match ($client?->sex) {
            1 => 'his',
            2 => 'her',
            default => 'their',
        };
        $assistanceType = Str::lower(trim($firstLetter->assistanceType?->name ?? ''));
        if (! str_ends_with($assistanceType, 'assistance')) {
            $assistanceType .= ' assistance';
        }

        return sprintf(
            'Payment for the %s of %s for %s %s %s et al.',
            $assistanceType,
            Str::upper($client?->full_name ?? ''),
            $possessivePronoun,
            Str::lower($firstLetter->relationship?->name ?? ''),
            Str::upper($firstLetter->beneficiary?->full_name ?? ''),
        );
    }
}
