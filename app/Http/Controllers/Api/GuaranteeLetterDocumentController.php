<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GuaranteeLetter;
use App\Services\GuaranteeLetterDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GuaranteeLetterDocumentController extends Controller
{
    public function download(GuaranteeLetter $guaranteeLetter, GuaranteeLetterDocumentService $documents): BinaryFileResponse
    {
        $path = $documents->archive($guaranteeLetter);
        $filename = preg_replace('/[^A-Za-z0-9_-]/', '-', $guaranteeLetter->gl_no).'-supporting-documents.zip';

        return response()->download($path, $filename, [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ])->deleteFileAfterSend(true);
    }

    public function show(Request $request, GuaranteeLetter $guaranteeLetter, int $document): StreamedResponse
    {
        $attachment = $guaranteeLetter->supportingDocuments()->findOrFail($document);
        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);

        return $disk->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ], $request->boolean('download') ? 'attachment' : 'inline');
    }
}
