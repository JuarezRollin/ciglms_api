<?php

namespace App\Services;

use App\Models\GuaranteeLetter;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;
use ZipArchive;

class GuaranteeLetterDocumentService
{
    /**
     * @param  list<UploadedFile>  $files
     * @param  list<array{disk: string, path: string}>  $storedFiles
     * @return list<int>
     */
    public function store(GuaranteeLetter $letter, array $files, User $actor, array &$storedFiles): array
    {
        if ($files === []) {
            return [];
        }

        if ($letter->supportingDocuments()->count() + count($files) > config('supporting_documents.max_files')) {
            throw ValidationException::withMessages(['supporting_documents' => 'A guarantee letter can have at most six supporting images.']);
        }

        $disk = config('supporting_documents.disk');
        $ids = [];
        foreach ($files as $file) {
            $directory = 'guarantee-letters/'.$letter->getKey().'/supporting-documents';
            $name = $file->hashName();
            $path = $directory.'/'.$name;
            $storedFiles[] = ['disk' => $disk, 'path' => $path];
            if ($file->storeAs($directory, $name, ['disk' => $disk, 'visibility' => 'private']) === false) {
                throw new RuntimeException('Unable to store supporting documents.');
            }
            $ids[] = $letter->supportingDocuments()->create([
                'uploaded_by' => $actor->getKey(),
                'disk' => $disk,
                'path' => $path,
                'original_name' => mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '_', basename(str_replace('\\', '/', $file->getClientOriginalName()))), 0, 255),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ])->getKey();
        }

        return $ids;
    }

    public function archive(GuaranteeLetter $letter): string
    {
        abort_unless(class_exists(ZipArchive::class), 503, 'Image archive downloads require the PHP ZIP extension.');
        $documents = $letter->supportingDocuments()->get();
        abort_if($documents->isEmpty(), 404, 'No supporting documents uploaded for this GL.');

        $path = tempnam(sys_get_temp_dir(), 'gl-documents-');
        if ($path === false) {
            throw new RuntimeException('Unable to prepare the image download.');
        }

        $archive = new ZipArchive;
        $opened = false;
        try {
            if ($archive->open($path, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Unable to create the image archive.');
            }
            $opened = true;
            foreach ($documents as $index => $document) {
                $disk = Storage::disk($document->disk);
                abort_unless($disk->exists($document->path), 404, 'A supporting image is missing from storage.');
                $contents = $disk->get($document->path);
                $name = ($index + 1).'-'.basename(str_replace('\\', '/', $document->original_name));
                if (! is_string($contents) || ! $archive->addFromString($name, $contents)) {
                    throw new RuntimeException('Unable to add a supporting image to the archive.');
                }
            }
            $opened = false;
            if (! $archive->close()) {
                throw new RuntimeException('Unable to finish the image archive.');
            }
            clearstatcache(true, $path);

            return $path;
        } catch (Throwable $exception) {
            if ($opened) {
                rescue(fn () => $archive->close());
            }
            if (is_file($path)) {
                unlink($path);
            }
            throw $exception;
        }
    }

    /** @param list<array{disk: string, path: string}> $storedFiles */
    public function cleanup(array $storedFiles): void
    {
        foreach ($storedFiles as $file) {
            try {
                if (! Storage::disk($file['disk'])->delete($file['path'])) {
                    report(new RuntimeException('Unable to clean up a failed supporting document upload.'));
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
