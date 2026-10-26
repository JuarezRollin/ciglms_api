<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\GlTransmittal;
use App\Models\GuaranteeLetter;
use App\Models\ServiceProvider;
use App\Models\Signatory;
use App\Models\Status;
use App\Models\Swo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GuaranteeLetterStatusListTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_list_filters_by_provider_signatory_category_creator_swo_and_created_date(): void
    {
        config(['jwt.secret' => 'gl-status-list-tests-only-secret-0123456789']);
        $this->seed();
        $this->actingAs(User::query()->firstOrFail(), 'api');

        $letter = GuaranteeLetter::query()->where('status_id', Status::FOR_REVIEW_BY_CRD_ID)->firstOrFail();
        $provider = ServiceProvider::query()->where('name', 'East Avenue Medical Center')->firstOrFail();
        $category = Category::query()->where('name', 'Funeral')->firstOrFail();
        $signatory = Signatory::query()->where('name', 'Monina Josefina H. Romualdez')->firstOrFail();
        $creator = User::query()->where('id', '!=', $letter->created_by)->firstOrFail();
        $swo = Swo::query()->where('id', '!=', $letter->swo_id)->firstOrFail();
        DB::table('guarantee_letters')->where('id', $letter->id)->update([
            'service_provider_id' => $provider->id,
            'signatory_id' => $signatory->id,
            'category_id' => $category->id,
            'created_by' => $creator->id,
            'swo_id' => $swo->id,
            'created_at' => '2001-01-01 15:30:00',
        ]);

        $options = $this->getJson('/api/v1/gl_creation/reviewed/filter-options')->assertOk();
        $this->assertContains($provider->id, array_column($options->json('service_providers'), 'id'));
        $this->assertContains($signatory->id, array_column($options->json('signatories'), 'id'));
        $this->assertContains($category->id, array_column($options->json('categories'), 'id'));
        $this->assertContains($creator->id, array_column($options->json('creators'), 'id'));
        $this->assertContains($swo->id, array_column($options->json('swos'), 'id'));

        $endpoint = '/api/v1/gl_creation/reviewed?';
        $filters = [
            'filter' => [
                'service_provider_id' => $provider->id,
                'signatory_id' => $signatory->id,
                'category_id' => $category->id,
                'created_by' => $creator->id,
                'swo_id' => $swo->id,
            ],
            'created_from' => '2001-01-01',
            'created_to' => '2001-01-01',
        ];
        $this->getJson($endpoint.http_build_query($filters))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $letter->id)
            ->assertJsonPath('data.0.signatory.name', $signatory->name)
            ->assertJsonPath('data.0.creator.name', $creator->name)
            ->assertJsonPath('data.0.swo.name', $swo->name);

        foreach ([
            'service_provider_id' => $letter->service_provider_id,
            'signatory_id' => $letter->signatory_id,
            'category_id' => $letter->category_id,
            'created_by' => $letter->created_by,
            'swo_id' => $letter->swo_id,
        ] as $field => $otherId) {
            $mismatchedFilters = $filters;
            $mismatchedFilters['filter'][$field] = $otherId;
            $this->getJson($endpoint.http_build_query($mismatchedFilters))
                ->assertOk()->assertJsonPath('meta.total', 0);
        }

        $this->getJson($endpoint.http_build_query(['search' => $letter->gl_no]))
            ->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson($endpoint.http_build_query(['created_from' => '2001-01-02', 'created_to' => '2001-01-01']))
            ->assertUnprocessable();
    }

    public function test_approved_list_uses_the_same_filters_and_approved_only_filter_options(): void
    {
        config(['jwt.secret' => 'gl-status-list-tests-only-secret-0123456789']);
        $this->seed();
        $this->actingAs(User::query()->firstOrFail(), 'api');

        $letter = GuaranteeLetter::query()->where('status_id', Status::APPROVED_ID)->firstOrFail();
        $provider = ServiceProvider::query()->where('id', '!=', $letter->service_provider_id)->firstOrFail();
        $category = Category::query()->where('id', '!=', $letter->category_id)->firstOrFail();
        $signatory = Signatory::query()->where('id', '!=', $letter->signatory_id)->firstOrFail();
        $creator = User::query()->where('id', '!=', $letter->created_by)->firstOrFail();
        $swo = Swo::query()->where('id', '!=', $letter->swo_id)->firstOrFail();
        DB::table('guarantee_letters')->where('id', $letter->id)->update([
            'service_provider_id' => $provider->id,
            'signatory_id' => $signatory->id,
            'category_id' => $category->id,
            'created_by' => $creator->id,
            'swo_id' => $swo->id,
            'created_at' => '2001-01-01 15:30:00',
        ]);

        $options = $this->getJson('/api/v1/gl_creation/approval/filter-options')->assertOk();
        foreach ([
            'service_providers' => $provider->id,
            'signatories' => $signatory->id,
            'categories' => $category->id,
            'creators' => $creator->id,
            'swos' => $swo->id,
        ] as $field => $id) {
            $this->assertContains($id, array_column($options->json($field), 'id'));
        }

        $filters = [
            'search' => $letter->gl_no,
            'filter' => [
                'service_provider_id' => $provider->id,
                'signatory_id' => $signatory->id,
                'category_id' => $category->id,
                'created_by' => $creator->id,
                'swo_id' => $swo->id,
            ],
            'created_from' => '2001-01-01',
            'created_to' => '2001-01-01',
        ];
        $endpoint = '/api/v1/gl_creation/approval?';
        $this->getJson($endpoint.http_build_query($filters))
            ->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $letter->id)
            ->assertJsonPath('data.0.swo.name', $swo->name);

        $filters['filter']['category_id'] = $letter->category_id;
        $this->getJson($endpoint.http_build_query($filters))->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson($endpoint.http_build_query(['created_from' => '2001-01-02', 'created_to' => '2001-01-01']))
            ->assertUnprocessable();
    }

    public function test_released_list_uses_the_same_filters_and_released_only_filter_options(): void
    {
        config(['jwt.secret' => 'gl-status-list-tests-only-secret-0123456789']);
        $this->seed();
        $this->actingAs(User::query()->firstOrFail(), 'api');

        $letter = GuaranteeLetter::query()->where('status_id', Status::RELEASED_ID)->whereDoesntHave('batches')->firstOrFail();
        $provider = ServiceProvider::query()->where('id', '!=', $letter->service_provider_id)->firstOrFail();
        $category = Category::query()->where('id', '!=', $letter->category_id)->firstOrFail();
        $signatory = Signatory::query()->where('id', '!=', $letter->signatory_id)->firstOrFail();
        $creator = User::query()->where('id', '!=', $letter->created_by)->firstOrFail();
        $swo = Swo::query()->where('id', '!=', $letter->swo_id)->firstOrFail();
        DB::table('guarantee_letters')->where('id', $letter->id)->update([
            'service_provider_id' => $provider->id,
            'signatory_id' => $signatory->id,
            'category_id' => $category->id,
            'created_by' => $creator->id,
            'swo_id' => $swo->id,
            'created_at' => '2001-01-01 15:30:00',
        ]);

        $options = $this->getJson('/api/v1/gl_creation/released/filter-options')->assertOk();
        foreach ([
            'service_providers' => $provider->id,
            'signatories' => $signatory->id,
            'categories' => $category->id,
            'creators' => $creator->id,
            'swos' => $swo->id,
        ] as $field => $id) {
            $this->assertContains($id, array_column($options->json($field), 'id'));
        }

        $filters = [
            'search' => $letter->gl_no,
            'filter' => [
                'service_provider_id' => $provider->id,
                'signatory_id' => $signatory->id,
                'category_id' => $category->id,
                'created_by' => $creator->id,
                'swo_id' => $swo->id,
            ],
            'created_from' => '2001-01-01',
            'created_to' => '2001-01-01',
        ];
        $endpoint = '/api/v1/gl_creation/released?';
        $this->getJson($endpoint.http_build_query($filters))
            ->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $letter->id)
            ->assertJsonPath('data.0.swo.name', $swo->name);

        $filters['filter']['category_id'] = $letter->category_id;
        $this->getJson($endpoint.http_build_query($filters))->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson($endpoint.http_build_query(['created_from' => '2001-01-02', 'created_to' => '2001-01-01']))
            ->assertUnprocessable();
    }

    public function test_endorsement_list_filters_and_shows_active_transmittal_details(): void
    {
        config(['jwt.secret' => 'gl-status-list-tests-only-secret-0123456789']);
        $this->seed();
        $user = User::query()->firstOrFail();
        $this->actingAs($user, 'api');

        $letter = GuaranteeLetter::query()->where('status_id', Status::FOR_ENDORSEMENT_ID)->whereDoesntHave('batches')->firstOrFail();
        $provider = ServiceProvider::query()->where('id', '!=', $letter->service_provider_id)->firstOrFail();
        $signatory = Signatory::query()->where('id', '!=', $letter->signatory_id)->firstOrFail();
        $creator = User::query()->where('id', '!=', $letter->created_by)->firstOrFail();
        $swo = Swo::query()->where('id', '!=', $letter->swo_id)->firstOrFail();
        DB::table('guarantee_letters')->where('id', $letter->id)->update([
            'service_provider_id' => $provider->id,
            'signatory_id' => $signatory->id,
            'created_by' => $creator->id,
            'swo_id' => $swo->id,
            'created_at' => '2001-01-01 15:30:00',
        ]);

        $activeTransmittal = $letter->transmittals()->whereNull('gl_transmittals.cancelled_at')->firstOrFail();
        $cancelledTransmittal = GlTransmittal::query()->create([
            'bundle_no' => 'FASS-01-01-0002',
            'category_id' => $letter->category_id,
            'created_by' => $user->id,
            'cancelled_at' => now(),
        ]);
        $letter->transmittals()->attach($cancelledTransmittal->id, ['position' => 1]);

        $options = $this->getJson('/api/v1/gl_creation/endorsement/filter-options')->assertOk();
        foreach ([
            'service_providers' => $provider->id,
            'signatories' => $signatory->id,
            'categories' => $letter->category_id,
            'creators' => $creator->id,
            'swos' => $swo->id,
        ] as $field => $id) {
            $this->assertContains($id, array_column($options->json($field), 'id'));
        }

        $filters = [
            'search' => $letter->gl_no,
            'filter' => [
                'service_provider_id' => $provider->id,
                'signatory_id' => $signatory->id,
                'category_id' => $letter->category_id,
                'created_by' => $creator->id,
                'swo_id' => $swo->id,
            ],
            'created_from' => '2001-01-01',
            'created_to' => '2001-01-01',
        ];
        $endpoint = '/api/v1/gl_creation/endorsement?';
        $this->getJson($endpoint.http_build_query($filters))
            ->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $letter->id)
            ->assertJsonPath('data.0.transmittals.0.id', $activeTransmittal->id)
            ->assertJsonPath('data.0.transmittals.0.bundle_no', $activeTransmittal->bundle_no)
            ->assertJsonPath('data.0.transmittals.0.creator.name', $activeTransmittal->creator->name)
            ->assertJsonCount(1, 'data.0.transmittals');

        $filters['filter']['service_provider_id'] = $letter->service_provider_id;
        $this->getJson($endpoint.http_build_query($filters))->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson($endpoint.http_build_query(['created_from' => '2001-01-02', 'created_to' => '2001-01-01']))
            ->assertUnprocessable();
    }

    public function test_cancelled_gl_list_includes_crd_and_rmcd_cancellations_only(): void
    {
        config(['jwt.secret' => 'gl-status-list-tests-only-secret-0123456789']);
        $this->seed();
        $this->actingAs(User::query()->firstOrFail(), 'api');

        $response = $this->getJson('/api/v1/guarantee-letters?status_scope=cancelled&per_page=100')->assertOk();

        $response->assertJsonPath('meta.total', 100);
        $statusIds = array_column(array_column($response->json('data'), 'status'), 'id');
        $this->assertEqualsCanonicalizing(
            [Status::CANCELLED_BY_CRD_ID, Status::CANCELLED_BY_RMCD_ID],
            array_values(array_unique($statusIds)),
        );

        $this->getJson('/api/v1/guarantee-letters?status_scope=all')->assertUnprocessable();
    }

    public function test_stage_list_filters_and_filter_options_cover_awaiting_soa_pullout_creation_and_cancelled(): void
    {
        config(['jwt.secret' => 'gl-status-list-tests-only-secret-0123456789']);
        $this->seed();
        $this->actingAs(User::query()->firstOrFail(), 'api');

        foreach ([Status::AWAITING_SOA_ID, Status::FOR_PULLOUT_ID, Status::FOR_CREATION_ID, Status::CANCELLED_BY_CRD_ID] as $index => $statusId) {
            $letter = GuaranteeLetter::query()->where('status_id', $statusId)->firstOrFail();
            $createdDate = sprintf('2001-01-%02d', $index + 1);
            DB::table('guarantee_letters')->where('id', $letter->id)->update(['created_at' => $createdDate.' 15:30:00']);

            $scope = $statusId === Status::CANCELLED_BY_CRD_ID
                ? ['status_scope' => 'cancelled']
                : ['filter' => ['status_id' => $statusId]];

            $options = $this->getJson('/api/v1/guarantee-letters/filter-options?'.http_build_query($scope))->assertOk();
            foreach ([
                'service_providers' => $letter->service_provider_id,
                'creators' => $letter->created_by,
                'signatories' => $letter->signatory_id,
                'categories' => $letter->category_id,
                'swos' => $letter->swo_id,
            ] as $field => $id) {
                $this->assertContains($id, array_column($options->json($field), 'id'));
            }

            $filters = [
                ...$scope,
                'search' => $letter->gl_no,
                'filter' => [
                    ...($scope['filter'] ?? []),
                    'service_provider_id' => $letter->service_provider_id,
                    'created_by' => $letter->created_by,
                    'signatory_id' => $letter->signatory_id,
                    'category_id' => $letter->category_id,
                    'swo_id' => $letter->swo_id,
                ],
                'created_from' => $createdDate,
                'created_to' => $createdDate,
            ];
            $endpoint = '/api/v1/guarantee-letters?';
            $this->getJson($endpoint.http_build_query($filters))
                ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $letter->id);

            $filters['created_from'] = '2001-02-01';
            $filters['created_to'] = '2001-02-01';
            $this->getJson($endpoint.http_build_query($filters))->assertOk()->assertJsonPath('meta.total', 0);
        }

        $cancelledOptions = $this->getJson('/api/v1/guarantee-letters/filter-options?status_scope=cancelled')->assertOk();
        $rmcdLetter = GuaranteeLetter::query()->where('status_id', Status::CANCELLED_BY_RMCD_ID)->firstOrFail();
        $this->assertContains($rmcdLetter->created_by, array_column($cancelledOptions->json('creators'), 'id'));

        $this->getJson('/api/v1/guarantee-letters?created_from=2001-02-01&created_to=2001-01-01')->assertUnprocessable();
    }

    public function test_gl_stage_lists_exclude_voucher_rmcd_reviews(): void
    {
        config(['jwt.secret' => 'gl-status-list-tests-only-secret-0123456789']);
        $this->seed();
        $this->actingAs(User::query()->firstOrFail(), 'api');

        $this->getJson('/api/v1/gl_creation/created')->assertNotFound();

        foreach ([
            'approval' => 'Approved',
            'released' => 'Released',
            'endorsement' => 'For Endorsement',
        ] as $route => $statusName) {
            $response = $this->getJson('/api/v1/gl_creation/'.$route)->assertOk();

            $response->assertJsonPath('meta.total', 50);
            $response->assertJsonPath('data.0.status.name', $statusName);
        }

        $reviewResponse = $this->getJson('/api/v1/gl_creation/reviewed?per_page=100')->assertOk();
        $reviewResponse->assertJsonPath('meta.total', 50);
        $reviewNames = array_map(fn (array $letter): string => $letter['status']['name'], $reviewResponse->json('data'));
        $this->assertContains('For Review by CRD', $reviewNames);
        $this->assertNotContains('For Review by RMCD', $reviewNames);

        DB::connection()->getPdo()->sqliteCreateFunction(
            'MONTH',
            static fn (string $date): int => (int) substr($date, 5, 2),
        );
        $dashboardResponse = $this->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonPath('data.review_voucher_count', 1)
            ->assertJsonPath('data.pending_actions.3', '1 vouchers awaiting review');
        $dashboardStatusNames = array_column($dashboardResponse->json('data.processing_status'), 'label');
        $this->assertTrue(
            array_search('For Review by CRD', $dashboardStatusNames, true)
            < array_search('For Review by RMCD', $dashboardStatusNames, true),
        );
        $this->assertTrue(
            array_search('For Endorsement', $dashboardStatusNames, true)
            < array_search('Awaiting SOA', $dashboardStatusNames, true),
        );

        foreach ([
            'reviewed' => 'For Review by CRD',
            'approval' => 'Approved',
            'released' => 'Released',
            'endorsement' => 'For Endorsement',
        ] as $route => $statusName) {
            $status = Status::query()->where('name', $statusName)->firstOrFail();
            $status->update(['name' => 'Renamed '.$statusName]);

            $this->getJson('/api/v1/gl_creation/'.$route)
                ->assertOk()
                ->assertJsonPath('meta.total', 50)
                ->assertJsonPath('data.0.status.id', $status->id)
                ->assertJsonPath('data.0.status.name', 'Renamed '.$statusName);
        }
    }
}
