<?php

namespace App\Services;

use App\Models\Signatory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class SignatoryCredentialService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function save(Signatory $signatory, array $data): Signatory
    {
        $storedPaths = [];
        $replacedPaths = [];

        try {
            $signatory = $signatory->getConnection()->transaction(function () use ($signatory, $data, &$storedPaths, &$replacedPaths): Signatory {
                if ($signatory->exists) {
                    $signatory = Signatory::query()->whereKey($signatory->getKey())->lockForUpdate()->firstOrFail();
                }

                $certificate = $data['p12_file'] ?? null;
                $password = $data['pnpki_password'] ?? $signatory->pnpki_password;
                if ($certificate !== null || array_key_exists('pnpki_password', $data)) {
                    $contents = $certificate?->get();
                    if ($contents === null && $signatory->p12_path !== null && Storage::disk('local')->exists($signatory->p12_path)) {
                        $contents = Storage::disk('local')->get($signatory->p12_path);
                    }

                    if (! is_string($contents) || ! is_string($password) || $password === ''
                        || ! openssl_pkcs12_read($contents, $details, $password)
                        || ! isset($details['cert'], $details['pkey'])) {
                        throw ValidationException::withMessages([
                            'pnpki_password' => 'The PNPKI password could not open this .p12 certificate. Check the file and password.',
                        ]);
                    }
                }

                $signatory->fill(Arr::except($data, ['p12_file', 'signature_image', 'pnpki_password']))->save();

                $credentials = [];
                foreach (['p12_file' => ['p12_path', 'p12'], 'signature_image' => ['signature_image_path', 'png']] as $field => [$column, $extension]) {
                    $file = $data[$field] ?? null;
                    if (! $file instanceof UploadedFile) {
                        continue;
                    }

                    $path = $file->storeAs('signatory-credentials/'.$signatory->getKey(), Str::uuid().'.'.$extension, 'local');
                    if ($path === false) {
                        throw new RuntimeException('Unable to store signatory credentials.');
                    }
                    $storedPaths[] = $path;
                    if ($signatory->{$column} !== null) {
                        $replacedPaths[] = $signatory->{$column};
                    }
                    $credentials[$column] = $path;
                }

                if (array_key_exists('pnpki_password', $data)) {
                    $credentials['pnpki_password'] = $password;
                }
                if ($credentials !== []) {
                    $signatory->forceFill($credentials)->save();
                }

                return $signatory;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);
            throw $exception;
        }

        Storage::disk('local')->delete($replacedPaths);

        return $signatory->refresh();
    }
}
