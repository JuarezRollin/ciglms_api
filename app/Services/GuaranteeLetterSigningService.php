<?php

namespace App\Services;

use App\Models\GuaranteeLetter;
use App\Models\Signatory;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GuaranteeLetterSigningService
{
    /** @param Closure(GuaranteeLetter): string $renderPdf */
    public function getOrSign(GuaranteeLetter $guaranteeLetter, Closure $renderPdf): string
    {
        return $guaranteeLetter->getConnection()->transaction(function () use ($guaranteeLetter, $renderPdf): string {
            $letter = GuaranteeLetter::query()->whereKey($guaranteeLetter->getKey())->lockForUpdate()->firstOrFail();

            if ($letter->signed_pdf_path !== null) {
                if (! Storage::disk('local')->exists($letter->signed_pdf_path)) {
                    throw new RuntimeException('The signed guarantee letter file is missing.');
                }

                return Storage::disk('local')->get($letter->signed_pdf_path);
            }

            $signatory = Signatory::query()->whereKey($letter->signatory_id)->lockForUpdate()->first();
            $password = $signatory?->pnpki_password;
            $certificatePath = $signatory?->p12_path;
            $imagePath = $signatory?->signature_image_path;
            if (! is_string($password) || $password === '' || ! is_string($certificatePath) || ! is_string($imagePath)
                || ! Storage::disk('local')->exists($certificatePath) || ! Storage::disk('local')->exists($imagePath)) {
                return $renderPdf($letter);
            }

            $projectFolder = config('services.pdf_signer.project_folder');
            if (! is_string($projectFolder) || $projectFolder === '') {
                throw new HttpResponseException(response()->json(['message' => 'PDF signing is not configured.'], 503));
            }

            $certificate = Storage::disk('local')->get($certificatePath);
            if (! openssl_pkcs12_read($certificate, $certificateDetails, $password) || ! isset($certificateDetails['cert'], $certificateDetails['pkey'])) {
                throw new HttpResponseException(response()->json(['message' => 'PDF signing certificate cannot be opened.'], 503));
            }

            $subject = openssl_x509_parse($certificateDetails['cert'])['subject']['CN'] ?? null;
            if (! is_string($subject) || $subject === '') {
                throw new HttpResponseException(response()->json(['message' => 'PDF signing certificate has no signer name.'], 503));
            }

            $pdf = $renderPdf($letter);
            $fields = [
                'projectFolder' => $projectFolder,
                'password' => $password,
                'signatureMode' => 'digital',
                'pageIndex' => (string) config('services.pdf_signer.page_index'),
                'x' => (string) config('services.pdf_signer.x'),
                'y' => (string) config('services.pdf_signer.y'),
                'width' => (string) config('services.pdf_signer.width'),
                'height' => (string) config('services.pdf_signer.height'),
                'signatureLayout' => 'LEFT_IMAGE',
                'signerName' => $subject,
                'labelFontSize' => '7',
                'nameFontSize' => '8',
                'dateFontSize' => '6',
                'showDate' => 'true',
                'fieldName' => 'GLSignature',
                'additionalSignaturePlacements' => (string) config('services.pdf_signer.additional_signature_placements'),
            ];

            try {
                $response = Http::withHeaders(['X-Project-Key' => $projectFolder])
                    ->timeout(60)
                    ->attach('p12File', $certificate, basename($certificatePath))
                    ->attach('signatureImageFile', Storage::disk('local')->get($imagePath), basename($imagePath))
                    ->attach('pdfFile', $pdf, "guarantee-letter-{$letter->getKey()}.pdf")
                    ->post(rtrim((string) config('services.pdf_signer.base_url'), '/').'/sign', $fields);
            } catch (ConnectionException) {
                throw new HttpResponseException(response()->json(['message' => 'PDF signing service is unavailable.'], 502));
            }

            $signedPdf = $response->body();
            if (! $response->successful()
                || ! str_starts_with(strtolower((string) $response->header('Content-Type')), 'application/pdf')
                || ! str_starts_with($signedPdf, '%PDF-')
                || $response->header('X-Signature-Mode') !== 'digital'
                || (int) $response->header('X-Signature-Count') < 1) {
                throw new HttpResponseException(response()->json([
                    'message' => 'PDF signing failed.',
                    'request_id' => $response->header('X-Request-ID'),
                ], 502));
            }

            $path = "signed-guarantee-letters/{$letter->getKey()}.pdf";
            if (! Storage::disk('local')->put($path, $signedPdf)) {
                throw new RuntimeException('Unable to store the signed guarantee letter.');
            }

            $letter->forceFill([
                'signed_pdf_path' => $path,
                'signed_pdf_filename' => "guarantee-letter-no-{$letter->gl_no}-{$letter->gl_date->toDateString()}.pdf",
                'signed_at' => now(),
            ])->save();

            return $signedPdf;
        });
    }
}
