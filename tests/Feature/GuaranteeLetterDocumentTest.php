<?php

namespace Tests\Feature;

use App\Models\AssistanceType;
use App\Models\Beneficiary;
use App\Models\Category;
use App\Models\Client;
use App\Models\GuaranteeLetter;
use App\Models\GuaranteeLetterSupportingDocument;
use App\Models\Office;
use App\Models\PsgcRegion;
use App\Models\Relationship;
use App\Models\ServiceProvider;
use App\Models\Signatory;
use App\Models\Status;
use App\Models\Swo;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;
use ZipArchive;

class GuaranteeLetterDocumentTest extends TestCase
{
    use RefreshDatabase;

    private GuaranteeLetter $letter;

    private User $encoder;

    public function createApplication(): Application
    {
        $app = parent::createApplication();
        if ($app['config']->get('database.default') !== 'sqlite'
            || $app['config']->get('database.connections.sqlite.database') !== ':memory:'
            || $app['config']->get('database.connections.sqlite.url')) {
            throw new RuntimeException('Document tests require SQLite :memory:. No database refresh was attempted.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => 'document-tests-only-secret-01234567890123456789', 'supporting_documents.disk' => 'local']);
        Storage::fake('local');
        $this->seed([RoleSeeder::class, StatusSeeder::class]);
        $region = PsgcRegion::create(['code_correspondence' => '130000000', 'code' => '1300000000', 'name' => 'Test Region']);
        $office = Office::create(['name' => 'Test Office', 'region_id' => $region->getKey()]);
        $this->encoder = User::factory()->create(['office_id' => $office->id]);
        $this->encoder->assignRole('encoder');
        $this->actingAs($this->encoder, 'api');
        $this->letter = GuaranteeLetter::create([
            'office_id' => $office->id, 'gl_no' => 'GL-DOCUMENT-TEST',
            'gl_date' => '2026-10-01', 'gl_validity' => '2026-11-01',
            'gl_amount' => '1250.00', 'status_id' => Status::AWAITING_SOA_ID,
            'client_id' => Client::create(['full_name' => 'Test Client', 'sex' => 1, 'address' => 'Test address'])->id,
            'beneficiary_id' => Beneficiary::create(['full_name' => 'Test Beneficiary'])->id,
            'address' => 'Test address',
            'relationship_id' => Relationship::create(['name' => 'Self'])->id,
            'category_id' => Category::create(['name' => 'Hospital Bill'])->id,
            'assistance_type_id' => AssistanceType::create(['name' => 'Medical'])->id,
            'service_provider_id' => ServiceProvider::create(['region_id' => $region->getKey(), 'name' => 'Hospital', 'address' => 'Test address'])->id,
            'signatory_id' => Signatory::create(['name' => 'Test Signatory', 'designation' => 'Director', 'minimum_amount' => 0, 'maximum_amount' => 100000])->id,
            'swo_id' => Swo::create(['name' => 'Test SWO'])->id,
            'created_by' => $this->encoder->id, 'date_received_gl' => '2026-10-01',
        ]);
    }

    public function test_six_images_are_saved_privately_with_the_soa_and_audited(): void
    {
        $files = array_map(fn ($index) => UploadedFile::fake()->image("receipt-$index.png"), range(1, 6));
        $this->record($files)->assertOk()
            ->assertJsonCount(6, 'data.supporting_documents')
            ->assertJsonPath('data.status.name', 'For Pullout')
            ->assertJsonPath('data.supporting_documents.0.uploaded_by', $this->encoder->name)
            ->assertJsonMissingPath('data.supporting_documents.0.path')
            ->assertJsonMissingPath('data.supporting_documents.0.disk');
        $this->assertDatabaseCount('guarantee_letter_supporting_documents', 6);
        foreach ($this->letter->supportingDocuments as $document) {
            Storage::disk('local')->assertExists($document->path);
            $this->assertSame('local', $document->disk);
            $this->assertStringStartsWith('guarantee-letters/'.$this->letter->id.'/supporting-documents/', $document->path);
            $this->assertSame($this->encoder->id, $document->uploaded_by);
        }
        $activity = Activity::where('event', 'record_soa_received')->sole();
        $this->assertSame(6, $activity->properties['supporting_documents_count']);
        $this->getJson("/api/v1/guarantee-letters/{$this->letter->id}")->assertOk()->assertJsonCount(6, 'data.supporting_documents');
        $this->getJson("/api/v1/guarantee-letters/{$this->letter->id}/audit-trail")->assertOk()->assertJsonPath('data.0.supporting_documents_count', 6);
    }

    public function test_existing_json_soa_without_images_still_works(): void
    {
        $this->postJson($this->endpoint(), ['action' => 'record_soa_received', 'date_received_soa' => '2026-10-02'])
            ->assertOk()->assertJsonPath('data.status.name', 'For Pullout')->assertJsonCount(0, 'data.supporting_documents');
        $this->assertNull($this->letter->fresh()->obligation_amount);
    }

    public function test_more_than_six_files_are_rejected_without_advancing_the_gl(): void
    {
        $this->record(array_map(fn () => UploadedFile::fake()->image('receipt.png'), range(1, 7)))
            ->assertUnprocessable()->assertJsonValidationErrors('supporting_documents');
        $this->assertUnchanged();
    }

    public function test_disguised_and_svg_files_are_rejected(): void
    {
        $this->record([UploadedFile::fake()->createWithContent('fake.png', '<?php echo "invalid";')])
            ->assertUnprocessable()->assertJsonValidationErrors('supporting_documents.0');
        $this->record([UploadedFile::fake()->createWithContent('unsafe.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')])
            ->assertUnprocessable()->assertJsonValidationErrors('supporting_documents.0');
        $this->assertUnchanged();
    }

    public function test_oversized_images_are_rejected(): void
    {
        $this->record([UploadedFile::fake()->image('receipt.png')->size(5121)])
            ->assertUnprocessable()->assertJsonValidationErrors('supporting_documents.0');
        $this->assertUnchanged();
    }

    public function test_other_actions_cannot_accept_documents(): void
    {
        $this->post($this->endpoint(), [
            'action' => 'update_obligation_amount', 'obligation_amount' => 1000,
            'supporting_documents' => [UploadedFile::fake()->image('receipt.png')],
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('supporting_documents');
        $this->assertUnchanged();
    }

    public function test_unauthorized_role_cannot_upload(): void
    {
        $this->actingAs(User::factory()->create(), 'api');
        $this->record([UploadedFile::fake()->image('receipt.png')])->assertForbidden();
        $this->assertUnchanged();
    }

    public function test_admin_can_upload_and_duplicate_soa_is_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'api');
        $this->record([UploadedFile::fake()->image('receipt.png')])->assertOk();
        $this->record([UploadedFile::fake()->image('second.png')])->assertConflict();
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('guarantee_letter_supporting_documents', 1);
    }

    public function test_private_preview_download_parent_binding_and_missing_file(): void
    {
        $this->record([UploadedFile::fake()->image('receipt.png')])->assertOk();
        $document = $this->letter->supportingDocuments()->sole();
        $url = "/api/v1/guarantee-letters/{$this->letter->id}/supporting-documents/{$document->id}";
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get($url.'?download=1')->assertOk()->assertDownload('receipt.png');
        $other = $this->letter->replicate();
        $other->gl_no = 'OTHER-GL';
        $other->save();
        $this->getJson("/api/v1/guarantee-letters/{$other->id}/supporting-documents/{$document->id}")->assertNotFound();
        Storage::disk('local')->delete($document->path);
        $this->getJson($url)->assertNotFound();
    }

    public function test_guest_cannot_view_documents(): void
    {
        $document = GuaranteeLetterSupportingDocument::factory()->for($this->letter)->create();
        auth('api')->forgetUser();
        $this->getJson("/api/v1/guarantee-letters/{$this->letter->id}/supporting-documents/{$document->id}")->assertUnauthorized();
    }

    public function test_database_failure_removes_written_files_and_rolls_back_soa(): void
    {
        GuaranteeLetterSupportingDocument::creating(function (): void {
            throw new RuntimeException('Simulated document metadata failure');
        });
        try {
            $this->record([UploadedFile::fake()->image('receipt.png')])->assertServerError();
            $this->assertUnchanged();
            $this->assertSame([], Storage::disk('local')->allFiles());
        } finally {
            GuaranteeLetterSupportingDocument::flushEventListeners();
        }
    }

    public function test_storage_failure_rolls_back_soa_and_cleans_up_previous_images(): void
    {
        $disk = Storage::disk('local');
        $filesystem = \Mockery::mock($disk)->makePartial();
        $filesystem->shouldReceive('putFileAs')->once()->passthru();
        $filesystem->shouldReceive('putFileAs')->once()->andReturn(false);
        app('filesystem')->set('local', $filesystem);
        $this->record([UploadedFile::fake()->image('first.png'), UploadedFile::fake()->image('second.png')])->assertServerError();
        $this->assertUnchanged();
        $this->assertSame([], $disk->allFiles());
    }

    public function test_download_images_archive_contains_only_this_gl_images_with_distinct_names(): void
    {
        $this->record([UploadedFile::fake()->image('receipt.png'), UploadedFile::fake()->image('receipt.png')])->assertOk();
        $other = $this->letter->replicate();
        $other->gl_no = 'OTHER-GL';
        $other->save();
        GuaranteeLetterSupportingDocument::factory()->for($other)->create();

        $response = $this->get('/api/v1/guarantee-letters/'.$this->letter->id.'/supporting-documents/download')
            ->assertOk()->assertDownload('GL-DOCUMENT-TEST-supporting-documents.zip')
            ->assertHeader('Content-Type', 'application/zip')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $path = $response->baseResponse->getFile()->getPathname();
        $archive = new ZipArchive;
        try {
            $this->assertSame(true, $archive->open($path));
            $this->assertSame(2, $archive->numFiles);
            foreach ($this->letter->supportingDocuments()->get() as $index => $document) {
                $this->assertSame(Storage::disk('local')->get($document->path), $archive->getFromName(($index + 1).'-receipt.png'));
            }
            $archive->close();
            ob_start();
            try {
                $response->baseResponse->sendContent();
            } finally {
                ob_end_clean();
            }
            $this->assertFileDoesNotExist($path);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_archive_download_is_unavailable_for_empty_or_missing_documents_and_guests(): void
    {
        $url = '/api/v1/guarantee-letters/'.$this->letter->id.'/supporting-documents/download';
        $this->getJson($url)->assertNotFound();
        GuaranteeLetterSupportingDocument::factory()->for($this->letter)->create();
        $this->getJson($url)->assertNotFound();
        auth('api')->forgetUser();
        $this->getJson($url)->assertUnauthorized();
    }

    private function endpoint(): string
    {
        return "/api/v1/guarantee-letters/{$this->letter->id}/actions";
    }

    /** @param list<UploadedFile> $files */
    private function record(array $files): TestResponse
    {
        return $this->post($this->endpoint(), [
            'action' => 'record_soa_received', 'date_received_soa' => '2026-10-02',
            'obligation_amount' => '', 'remarks' => 'SOA documents received', 'supporting_documents' => $files,
        ], ['Accept' => 'application/json']);
    }

    private function assertUnchanged(): void
    {
        $this->assertSame(Status::AWAITING_SOA_ID, $this->letter->fresh()->status_id);
        $this->assertNull($this->letter->fresh()->date_received_soa);
        $this->assertDatabaseCount('guarantee_letter_supporting_documents', 0);
    }
}
