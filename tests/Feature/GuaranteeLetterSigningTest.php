<?php

namespace Tests\Feature;

use App\Models\GuaranteeLetter;
use App\Models\Signatory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GuaranteeLetterSigningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_first_print_saves_signed_pdf_and_later_print_returns_same_pdf_after_edits(): void
    {
        $this->seed();
        $letter = GuaranteeLetter::query()->firstOrFail();
        $filename = 'guarantee-letter-no-'.$letter->gl_no.'-'.$letter->gl_date->toDateString().'.pdf';
        $this->actingAs($letter->creator, 'api');
        $this->configureSigning();
        Http::preventStrayRequests();
        Http::fake([
            'https://pdf-signer-staging.dswd.gov.ph/sign' => Http::response('%PDF-1.4 signed once', 200, [
                'Content-Type' => 'application/pdf',
                'X-Signature-Mode' => 'digital',
                'X-Signature-Count' => '1',
            ]),
        ]);

        $this->getJson("/api/v1/guarantee-letters/printGl/{$letter->id}")
            ->assertOk()
            ->assertJsonPath('data.filename', $filename)
            ->assertJsonPath('data.pdf', base64_encode('%PDF-1.4 signed once'));

        $this->assertDatabaseHas('guarantee_letters', [
            'id' => $letter->id,
            'signed_pdf_path' => "signed-guarantee-letters/{$letter->id}.pdf",
        ]);
        Storage::disk('local')->assertExists("signed-guarantee-letters/{$letter->id}.pdf");
        Http::assertSent(function (Request $request): bool {
            $parts = collect($request->data());

            return $request->hasHeader('X-Project-Key', 'ciglms-staging-test')
                && $request->hasFile('p12File')
                && $request->hasFile('signatureImageFile', 'image fixture')
                && $request->hasFile('pdfFile')
                && preg_match_all('/\/Type\s*\/Page\b/', (string) $parts->firstWhere('name', 'pdfFile')['contents']) === 2
                && $parts->contains(fn (array $part): bool => $part['name'] === 'signatureMode' && $part['contents'] === 'digital')
                && $parts->contains(fn (array $part): bool => $part['name'] === 'signerName' && $part['contents'] === 'Test Signer')
                && $parts->contains(fn (array $part): bool => $part['name'] === 'x' && $part['contents'] === '40')
                && $parts->contains(fn (array $part): bool => $part['name'] === 'y' && $part['contents'] === '150')
                && $parts->contains(fn (array $part): bool => $part['name'] === 'width' && $part['contents'] === '180')
                && $parts->contains(fn (array $part): bool => $part['name'] === 'additionalSignaturePlacements' && $part['contents'] === '1,40,150,180,55');
        });

        $letter->update(['gl_amount' => 9999, 'gl_date' => '2026-10-07']);
        $letter->signatory->forceFill(['pnpki_password' => null])->save();
        $this->getJson("/api/v1/guarantee-letters/printGl/{$letter->id}")
            ->assertOk()
            ->assertJsonPath('data.filename', $filename)
            ->assertJsonPath('data.pdf', base64_encode('%PDF-1.4 signed once'));
        Http::assertSentCount(1);
    }

    public function test_signing_failure_returns_502_without_saving_a_pdf(): void
    {
        $this->seed();
        $letter = GuaranteeLetter::query()->firstOrFail();
        $this->actingAs($letter->creator, 'api');
        $this->configureSigning();
        Http::preventStrayRequests();
        Http::fake([
            'https://pdf-signer-staging.dswd.gov.ph/sign' => Http::response(['error' => 'Certificate rejected'], 400),
        ]);

        $this->getJson("/api/v1/guarantee-letters/printGl/{$letter->id}")
            ->assertStatus(502)
            ->assertJsonPath('message', 'PDF signing failed.');

        $this->assertNull($letter->fresh()->signed_pdf_path);
        Storage::disk('local')->assertMissing("signed-guarantee-letters/{$letter->id}.pdf");
    }

    public function test_unconfigured_signing_returns_503_without_contacting_service(): void
    {
        $this->seed();
        $letter = GuaranteeLetter::query()->firstOrFail();
        $this->actingAs($letter->creator, 'api');
        $this->configureSigning();
        config()->set('services.pdf_signer.project_folder', null);
        Http::preventStrayRequests();

        $this->getJson("/api/v1/guarantee-letters/printGl/{$letter->id}")
            ->assertServiceUnavailable()
            ->assertJsonPath('message', 'PDF signing is not configured.');

        Http::assertNothingSent();
    }

    public function test_invalid_certificate_returns_503_without_contacting_service(): void
    {
        $this->seed();
        $letter = GuaranteeLetter::query()->firstOrFail();
        $this->actingAs($letter->creator, 'api');
        $this->configureSigning();
        Storage::disk('local')->put($letter->signatory->p12_path, 'invalid certificate');
        Http::preventStrayRequests();

        $this->getJson("/api/v1/guarantee-letters/printGl/{$letter->id}")
            ->assertServiceUnavailable()
            ->assertJsonPath('message', 'PDF signing certificate cannot be opened.');

        Http::assertNothingSent();
        $this->assertNull($letter->fresh()->signed_pdf_path);
    }

    public function test_image_only_response_is_rejected_without_saving_a_pdf(): void
    {
        $this->seed();
        $letter = GuaranteeLetter::query()->firstOrFail();
        $this->actingAs($letter->creator, 'api');
        $this->configureSigning();
        Http::preventStrayRequests();
        Http::fake([
            'https://pdf-signer-staging.dswd.gov.ph/sign' => Http::response('%PDF-1.4 image only', 200, [
                'Content-Type' => 'application/pdf',
                'X-Signature-Mode' => 'electronic',
                'X-Signature-Count' => '0',
            ]),
        ]);

        $this->getJson("/api/v1/guarantee-letters/printGl/{$letter->id}")
            ->assertStatus(502)
            ->assertJsonPath('message', 'PDF signing failed.');

        $this->assertNull($letter->fresh()->signed_pdf_path);
        Storage::disk('local')->assertMissing("signed-guarantee-letters/{$letter->id}.pdf");
    }

    public function test_missing_saved_pdf_returns_500_without_signing_again(): void
    {
        $this->seed();
        $letter = GuaranteeLetter::query()->firstOrFail();
        $this->actingAs($letter->creator, 'api');
        Storage::fake('local');
        $letter->forceFill([
            'signed_pdf_path' => "signed-guarantee-letters/{$letter->id}.pdf",
            'signed_pdf_filename' => 'original.pdf',
            'signed_at' => now(),
        ])->save();
        Http::preventStrayRequests();

        $this->getJson("/api/v1/guarantee-letters/printGl/{$letter->id}")
            ->assertInternalServerError()
            ->assertJsonPath('message', 'Server error.');

        Http::assertNothingSent();
    }

    public function test_signing_uses_the_credentials_of_each_letters_assigned_signatory(): void
    {
        $this->seed();
        $first = GuaranteeLetter::query()->firstOrFail();
        $other = Signatory::query()->create([
            'name' => 'Second Signatory', 'designation' => 'Director',
            'minimum_amount' => 0, 'maximum_amount' => 99999999,
        ]);
        $second = $first->replicate();
        $second->forceFill(['gl_no' => 'GL-SECOND-SIGNER', 'signatory_id' => $other->id])->save();
        $this->actingAs($first->creator, 'api');
        $this->configureSigning($first->signatory, 'First Certificate Owner', 'first password', 'first signature image');
        $this->configureSigning($other, 'Second Certificate Owner', 'second password', 'second signature image');
        Http::preventStrayRequests();
        Http::fake(['https://pdf-signer-staging.dswd.gov.ph/sign' => Http::response('%PDF-1.4 signed', 200, [
            'Content-Type' => 'application/pdf', 'X-Signature-Mode' => 'digital', 'X-Signature-Count' => '1',
        ])]);

        $this->getJson("/api/v1/guarantee-letters/printGl/{$first->id}")->assertOk();
        $this->getJson("/api/v1/guarantee-letters/printGl/{$second->id}")->assertOk();

        foreach ([[$first, 'First Certificate Owner', 'first password'], [$second, 'Second Certificate Owner', 'second password']] as [$letter, $name, $password]) {
            Http::assertSent(function (Request $request) use ($letter, $name, $password): bool {
                $parts = collect($request->data());

                return $request->hasFile('p12File', Storage::disk('local')->get($letter->signatory->p12_path))
                    && $request->hasFile('signatureImageFile', Storage::disk('local')->get($letter->signatory->signature_image_path))
                    && $parts->contains(fn (array $part): bool => $part['name'] === 'signerName' && $part['contents'] === $name)
                    && $parts->contains(fn (array $part): bool => $part['name'] === 'password' && $part['contents'] === $password);
            });
        }
        Http::assertSentCount(2);
    }

    public function test_missing_signatory_credentials_produce_an_unsigned_pdf_and_can_be_signed_later(): void
    {
        $this->seed();
        $letter = GuaranteeLetter::query()->firstOrFail();
        $this->actingAs($letter->creator, 'api');
        config()->set('services.pdf_signer.project_folder', null);
        Http::preventStrayRequests();

        $response = $this->getJson("/api/v1/guarantee-letters/printGl/{$letter->id}")
            ->assertOk()
            ->assertJsonPath('data.filename', "guarantee-letter-no-{$letter->gl_no}-{$letter->gl_date->toDateString()}.pdf");
        $this->assertStringStartsWith('%PDF-', base64_decode($response->json('data.pdf')));
        Http::assertNothingSent();
        $this->assertNull($letter->fresh()->signed_pdf_path);
        Storage::disk('local')->assertMissing("signed-guarantee-letters/{$letter->id}.pdf");

        $this->configureSigning();
        Http::fake(['https://pdf-signer-staging.dswd.gov.ph/sign' => Http::response('%PDF-1.4 signed later', 200, [
            'Content-Type' => 'application/pdf', 'X-Signature-Mode' => 'digital', 'X-Signature-Count' => '1',
        ])]);
        $this->getJson("/api/v1/guarantee-letters/printGl/{$letter->id}")
            ->assertOk()
            ->assertJsonPath('data.pdf', base64_encode('%PDF-1.4 signed later'));
        $this->assertSame("signed-guarantee-letters/{$letter->id}.pdf", $letter->fresh()->signed_pdf_path);
        Http::assertSentCount(1);
    }

    public function test_missing_signatory_resource_file_produces_an_unsigned_pdf(): void
    {
        $this->seed();
        $letter = GuaranteeLetter::query()->firstOrFail();
        $this->actingAs($letter->creator, 'api');
        $this->configureSigning();
        Storage::disk('local')->delete($letter->signatory->signature_image_path);
        Http::preventStrayRequests();

        $response = $this->getJson("/api/v1/guarantee-letters/printGl/{$letter->id}")->assertOk();
        $this->assertStringStartsWith('%PDF-', base64_decode($response->json('data.pdf')));
        $this->assertNull($letter->fresh()->signed_pdf_path);
        Http::assertNothingSent();
    }

    private function configureSigning(?Signatory $signatory = null, string $name = 'Test Signer', string $password = 'test password', string $signatureImage = 'image fixture'): void
    {
        $signatory ??= GuaranteeLetter::query()->firstOrFail()->signatory;
        $privateKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $request = openssl_csr_new(['commonName' => $name], $privateKey);
        $certificate = openssl_csr_sign($request, null, $privateKey, 1);
        openssl_pkcs12_export($certificate, $bundle, $privateKey, $password);
        $certificatePath = "signatory-credentials/{$signatory->id}/certificate.p12";
        $imagePath = "signatory-credentials/{$signatory->id}/signature.png";
        Storage::disk('local')->put($certificatePath, $bundle);
        Storage::disk('local')->put($imagePath, $signatureImage);
        $signatory->forceFill([
            'p12_path' => $certificatePath, 'signature_image_path' => $imagePath, 'pnpki_password' => $password,
        ])->save();
        config()->set('services.pdf_signer.project_folder', 'ciglms-staging-test');
    }
}
