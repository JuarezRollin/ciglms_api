<?php

namespace Tests\Feature;

use App\Models\Signatory;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SignatorySigningCredentialsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'api');
        Storage::fake('local');
    }

    public function test_admin_can_upload_private_credentials_without_exposing_or_logging_secrets(): void
    {
        $this->post('/api/v1/signatories', $this->payload(), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.has_p12_certificate', true)
            ->assertJsonPath('data.has_signature_image', true)
            ->assertJsonPath('data.has_pnpki_password', true)
            ->assertJsonMissingPath('data.p12_path')
            ->assertJsonMissingPath('data.signature_image_path')
            ->assertJsonMissingPath('data.position')
            ->assertJsonMissingPath('data.pnpki_password');

        $signatory = Signatory::query()->sole();
        Storage::disk('local')->assertExists([$signatory->p12_path, $signatory->signature_image_path]);
        $this->assertSame('test PNPKI password', $signatory->pnpki_password);
        $this->assertNotSame('test PNPKI password', $signatory->getRawOriginal('pnpki_password'));
        $this->assertArrayNotHasKey('pnpki_password', $signatory->toArray());
        $this->assertArrayNotHasKey('p12_path', $signatory->toArray());
        $this->assertStringNotContainsString('pnpki_password', DB::table('activity_log')->get()->toJson());
        $this->assertSame('Director', $signatory->designation);
    }

    public function test_new_signatory_can_be_created_without_signing_credentials(): void
    {
        $this->postJson('/api/v1/signatories', $this->metadata())
            ->assertCreated()
            ->assertJsonPath('data.has_p12_certificate', false)
            ->assertJsonPath('data.has_signature_image', false)
            ->assertJsonPath('data.has_pnpki_password', false);
        $this->assertDatabaseCount('signatories', 1);
        $this->assertSame([], Storage::disk('local')->allFiles());

        $signatory = Signatory::query()->sole();
        $this->putJson('/api/v1/signatories/'.$signatory->id, [...$this->metadata(), 'designation' => 'Assistant Director'])
            ->assertOk()
            ->assertJsonPath('data.designation', 'Assistant Director')
            ->assertJsonPath('data.has_p12_certificate', false);
    }

    public function test_signatory_initials_are_generated_and_can_be_edited(): void
    {
        $this->postJson('/api/v1/signatories', [...$this->metadata(), 'name' => 'Lea B. Bangalan'])
            ->assertCreated()
            ->assertJsonPath('data.initials', 'LBB');

        $signatory = Signatory::query()->sole();
        $this->putJson('/api/v1/signatories/'.$signatory->id, [
            ...$this->metadata(),
            'name' => 'Lea B. Bangalan',
            'initials' => 'lb',
        ])->assertOk()->assertJsonPath('data.initials', 'LB');

        $this->getJson('/api/v1/signatories?search=LB')
            ->assertOk()->assertJsonPath('data.0.initials', 'LB');
        $this->putJson('/api/v1/signatories/'.$signatory->id, [
            ...$this->metadata(),
            'name' => 'Lea B. Bangalan',
            'initials' => 'L B',
        ])->assertUnprocessable()->assertJsonValidationErrors('initials');
        $this->assertSame('LB', $signatory->fresh()->initials);
    }

    public function test_designation_is_required_when_creating_and_updating(): void
    {
        $payload = $this->payload();
        $payload['designation'] = '';
        $this->post('/api/v1/signatories', $payload, ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('designation');
        $this->assertDatabaseCount('signatories', 0);

        $this->post('/api/v1/signatories', $this->payload(), ['Accept' => 'application/json'])->assertCreated();
        $signatory = Signatory::query()->sole();
        $this->putJson('/api/v1/signatories/'.$signatory->id, [...$this->metadata(), 'designation' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('designation');
        $this->assertSame('Director', $signatory->fresh()->designation);
    }

    public function test_migration_preserves_titles_in_a_single_designation_column(): void
    {
        Schema::table('signatories', function (Blueprint $table): void {
            $table->string('position')->nullable();
        });
        DB::table('signatories')->insert([
            ['name' => 'Legacy Signatory', 'designation' => 'Authorized Signatory', 'position' => 'Director III', 'minimum_amount' => 0, 'maximum_amount' => 10000],
            ['name' => 'Updated Signatory', 'designation' => 'Director IV', 'position' => 'Authorized Signatory', 'minimum_amount' => 0, 'maximum_amount' => 10000],
        ]);
        $migration = require database_path('migrations/2026_10_16_084454_consolidate_signatory_position_into_designation.php');
        $migration->up();

        $this->assertFalse(Schema::hasColumn('signatories', 'position'));
        $this->assertDatabaseHas('signatories', ['name' => 'Legacy Signatory', 'designation' => 'Director III']);
        $this->assertDatabaseHas('signatories', ['name' => 'Updated Signatory', 'designation' => 'Director IV']);
    }

    public function test_pnpki_password_preserves_leading_and_trailing_spaces(): void
    {
        $password = '  PNPKI password with spaces  ';
        $this->post('/api/v1/signatories', $this->payload($password), ['Accept' => 'application/json'])->assertCreated();
        $this->assertSame($password, Signatory::query()->sole()->pnpki_password);
    }

    public function test_invalid_password_is_rejected_before_creating_a_signatory_or_storing_files(): void
    {
        $payload = $this->payload();
        $payload['pnpki_password'] = 'incorrect password';
        $this->post('/api/v1/signatories', $payload, ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('pnpki_password');
        $this->assertDatabaseCount('signatories', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_signature_image_must_be_a_png(): void
    {
        $payload = $this->payload();
        $payload['signature_image'] = UploadedFile::fake()->create('signature.png', 1, 'text/plain');
        $this->post('/api/v1/signatories', $payload, ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('signature_image');
        $this->assertDatabaseCount('signatories', 0);
    }

    public function test_edit_keeps_credentials_when_omitted_and_replaces_files_when_uploaded(): void
    {
        $this->post('/api/v1/signatories', $this->payload(), ['Accept' => 'application/json'])->assertCreated();
        $signatory = Signatory::query()->sole();
        $oldCertificate = $signatory->p12_path;
        $oldImage = $signatory->signature_image_path;

        $this->putJson('/api/v1/signatories/'.$signatory->id, $this->metadata())
            ->assertOk()->assertJsonPath('data.has_pnpki_password', true);
        $this->assertSame($oldCertificate, $signatory->fresh()->p12_path);
        $this->assertSame('test PNPKI password', $signatory->fresh()->pnpki_password);

        $payload = $this->payload('replacement password');
        $payload['_method'] = 'PUT';
        $this->post('/api/v1/signatories/'.$signatory->id, $payload, ['Accept' => 'application/json'])->assertOk();
        $signatory->refresh();
        $this->assertNotSame($oldCertificate, $signatory->p12_path);
        $this->assertSame('replacement password', $signatory->pnpki_password);
        Storage::disk('local')->assertMissing([$oldCertificate, $oldImage]);
        Storage::disk('local')->assertExists([$signatory->p12_path, $signatory->signature_image_path]);
    }

    public function test_failed_replacement_keeps_existing_files_and_password(): void
    {
        $this->post('/api/v1/signatories', $this->payload(), ['Accept' => 'application/json'])->assertCreated();
        $signatory = Signatory::query()->sole();
        $certificatePath = $signatory->p12_path;
        $this->putJson('/api/v1/signatories/'.$signatory->id, [...$this->metadata(), 'pnpki_password' => 'wrong'])
            ->assertUnprocessable()->assertJsonValidationErrors('pnpki_password');
        $this->assertSame($certificatePath, $signatory->fresh()->p12_path);
        $this->assertSame('test PNPKI password', $signatory->fresh()->pnpki_password);
        Storage::disk('local')->assertExists($certificatePath);
    }

    public function test_non_admin_cannot_upload_signatory_credentials(): void
    {
        $this->post('/api/v1/signatories', $this->payload(), ['Accept' => 'application/json'])->assertCreated();
        $signatory = Signatory::query()->sole();
        $this->actingAs(User::factory()->create(), 'api');
        $this->post('/api/v1/signatories', $this->payload(), ['Accept' => 'application/json'])->assertForbidden();
        $this->putJson('/api/v1/signatories/'.$signatory->id, [...$this->metadata(), 'pnpki_password' => 'changed'])->assertForbidden();
        $this->assertSame('test PNPKI password', $signatory->fresh()->pnpki_password);
        $this->assertDatabaseCount('signatories', 1);
    }

    /** @return array<string, mixed> */
    private function payload(string $password = 'test PNPKI password'): array
    {
        $privateKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $request = openssl_csr_new(['commonName' => 'Test Signatory'], $privateKey);
        $certificate = openssl_csr_sign($request, null, $privateKey, 1);
        openssl_pkcs12_export($certificate, $bundle, $privateKey, $password);

        return [
            ...$this->metadata(),
            'p12_file' => UploadedFile::fake()->createWithContent('certificate.p12', $bundle),
            'signature_image' => UploadedFile::fake()->image('signature.png'),
            'pnpki_password' => $password,
        ];
    }

    /** @return array<string, mixed> */
    private function metadata(): array
    {
        return [
            'name' => 'Test Signatory', 'designation' => 'Director', 'office' => 'CIP',
            'minimum_amount' => 0, 'maximum_amount' => 100000, 'is_active' => true,
        ];
    }
}
