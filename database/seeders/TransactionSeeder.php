<?php

namespace Database\Seeders;

use App\Models\AssistanceType;
use App\Models\Batch;
use App\Models\Beneficiary;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Cheque;
use App\Models\Client;
use App\Models\Dv;
use App\Models\FundSource;
use App\Models\GlTransmittal;
use App\Models\GuaranteeLetter;
use App\Models\Office;
use App\Models\Ors;
use App\Models\Relationship;
use App\Models\ServiceProvider;
use App\Models\Signatory;
use App\Models\Status;
use App\Models\Swo;
use App\Models\User;
use App\Models\Voucher;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    private CarbonImmutable $oldestCreationDate;

    private CarbonImmutable $latestEventDate;

    private int $encoderId;

    public function run(): void
    {
        $today = CarbonImmutable::today('Asia/Manila');
        $this->oldestCreationDate = $today->subWeeks(2)->setTime(8, 0);
        $this->latestEventDate = $today->subDay()->setTime(20, 0);
        $this->encoderId = User::query()->where('email', 'john.reyes@dswd.gov.ph')->firstOrFail()->id;

        $stages = [
            'For Review by CRD', 'Approved', 'Released', 'On Hold',
            'Cancelled by CRD', 'For Endorsement', 'Awaiting SOA', 'For Pullout',
            'For Creation', 'For Compliance', 'For Review by RMCD', 'On Process',
            'For Obligation', 'For Pre-Audit', 'For Crediting', 'Paid', 'Cancelled by RMCD',
        ];

        $sequence = 1;
        $glsByStatus = [];

        foreach ($stages as $status) {
            $glsByStatus[$status] = [];

            $copies = 50;

            for ($copy = 0; $copy < $copies; $copy++) {
                $createdAt = $this->oldestCreationDate->addDays($copy % 14);
                $glsByStatus[$status][] = $this->gl(
                    $sequence++,
                    $status,
                    $createdAt,
                    $this->datesForStatus($status, $createdAt),
                );
            }
        }

        $this->transmittals($glsByStatus['For Endorsement']);
        $this->transmittals($glsByStatus['Awaiting SOA'], received: true);

        $this->batch(1, $glsByStatus['For Review by RMCD']);
        $this->batch(2, $glsByStatus['On Process']);
        $this->batch(3, $glsByStatus['For Obligation']);
        $this->batch(4, $glsByStatus['For Pre-Audit'], withOrsAndDv: true);
        $this->batch(5, $glsByStatus['For Crediting'], withOrsAndDv: true);
        $this->batch(6, $glsByStatus['Paid'], withOrsAndDv: true, withCheque: true);
        $this->batch(7, $glsByStatus['Cancelled by RMCD']);
    }

    /** @param array<string, string> $dates */
    private function gl(int $sequence, string $status, CarbonImmutable $createdAt, array $dates): GuaranteeLetter
    {
        $amount = (string) (5000 + ($sequence * 750));
        $isGlCreationStage = in_array($status, [
            'For Review by CRD', 'Approved', 'Released', 'On Hold',
            'Cancelled by CRD', 'For Endorsement',
        ], true);
        $hasVoucherBatch = in_array($status, [
            'For Review by RMCD', 'On Process', 'For Obligation', 'For Pre-Audit',
            'For Crediting', 'Paid', 'Cancelled by RMCD',
        ], true);

        $letter = GuaranteeLetter::query()->updateOrCreate(
            ['gl_no' => sprintf('GL-2026-%04d', $sequence)],
            [
                'office_id' => Office::query()->where('name', 'Central Office')->value('id'),
                'gl_date' => $createdAt->toDateString(),
                'client_id' => Client::query()->orderBy('id')->skip(($sequence - 1) % 10)->value('id'),
                'beneficiary_id' => Beneficiary::query()->orderBy('id')->skip(($sequence - 1) % 10)->value('id'),
                'address' => 'Metro Manila',
                'relationship_id' => Relationship::query()->where('name', 'Spouse')->value('id'),
                'service_provider_id' => ServiceProvider::query()->where('name', 'Philippine General Hospital')->value('id'),
                'branch_id' => Branch::query()->orderBy('id')->value('id'),
                'fund_source_id' => $hasVoucherBatch ? FundSource::query()->where('name', 'AICS')->value('id') : null,
                'assistance_type_id' => AssistanceType::query()->where('name', 'Medical')->value('id'),
                'status_id' => Status::query()->where('name', $status)->value('id'),
                'gl_amount' => $amount,
                'obligation_amount' => $hasVoucherBatch ? $amount : null,
                'signatory_id' => Signatory::query()->coveringAmount($amount)->where('is_active', true)->value('id'),
                'swo_id' => Swo::query()->orderBy('id')->value('id'),
                'gl_validity' => $createdAt->addDays(30)->toDateString(),
                'category_id' => Category::query()->where('name', 'Hospital Bill')->value('id'),
                'date_received_gl' => $isGlCreationStage ? null : $this->eventAt($createdAt, 5),
                'received_by' => $isGlCreationStage ? null : $this->encoderId,
                'created_by' => User::query()->orderBy('id')->value('id'),
                'created_at' => $createdAt->format('Y-m-d H:i:s'),
            ],
        );

        if ($dates !== []) {
            $letter->forceFill($dates)->save();
        }

        return $letter;
    }

    /** @param list<GuaranteeLetter> $gls */
    private function transmittals(array $gls, bool $received = false): void
    {
        $creatorId = User::query()->orderBy('id')->value('id');

        foreach (array_chunk($gls, 25) as $index => $letters) {
            $transmittal = GlTransmittal::query()->updateOrCreate(
                ['bundle_no' => sprintf('FAS-SAMPLE-%04d', $index + ($received ? 3 : 1))],
                [
                    'category_id' => $letters[0]->category_id,
                    'created_by' => $creatorId,
                    'received_by' => $received ? $this->encoderId : null,
                    'cancelled_by' => null,
                    'cancelled_at' => null,
                ],
            );

            $transmittal->gls()->sync(collect($letters)
                ->mapWithKeys(fn (GuaranteeLetter $letter, int $position): array => [
                    $letter->id => ['position' => $position + 1],
                ])->all());
        }
    }

    /** @param list<GuaranteeLetter> $gls */
    private function batch(
        int $sequence,
        array $gls,
        bool $withOrsAndDv = false,
        bool $withCheque = false,
    ): void {
        $voucher = Voucher::query()->firstOrCreate(['voucher_no' => sprintf('V-2026-%04d', $sequence)]);
        $ors = $withOrsAndDv
            ? Ors::query()->firstOrCreate(['ors_no' => sprintf('ORS-2026-%04d', $sequence)])
            : null;
        $dv = $withOrsAndDv
            ? Dv::query()->firstOrCreate(['dv_no' => sprintf('DV-2026-%04d', $sequence)])
            : null;
        $cheque = $withCheque
            ? Cheque::query()->updateOrCreate(
                ['cheque_no' => sprintf('CHK-2026-%04d', $sequence)],
                ['cheque_date' => $this->latestEventDate->toDateString()],
            )
            : null;

        $batch = Batch::query()->updateOrCreate(
            ['remarks' => sprintf('Sample accounting batch %d', $sequence)],
            [
                'voucher_id' => $voucher->id,
                'ors_id' => $ors?->id,
                'dv_id' => $dv?->id,
                'cheque_id' => $cheque?->id,
                'created_by' => User::query()->orderBy('id')->value('id'),
            ],
        );
        $batch->syncGls(array_map(fn (GuaranteeLetter $gl): int => $gl->id, $gls));
    }

    /** @return array<string, string> */
    private function datesForStatus(string $status, CarbonImmutable $createdAt): array
    {
        $reviewed = ['date_reviewed_by_creation' => $this->eventAt($createdAt, 1)];
        $approved = [...$reviewed, 'date_approved' => $this->eventAt($createdAt, 2)];
        $released = [...$approved, 'date_released' => $this->eventAt($createdAt, 3)];
        $endorsed = [...$released, 'date_endorsed' => $this->eventAt($createdAt, 4)];

        return match ($status) {
            'Approved' => $reviewed,
            'Released' => $approved,
            'On Hold' => ['date_onhold' => $this->eventAt($createdAt, 1)],
            'Cancelled by CRD' => ['date_cancelled_by_creation' => $this->eventAt($createdAt, 1)],
            'For Endorsement' => $released,
            'Awaiting SOA' => $endorsed,
            'For Pullout' => [...$endorsed, 'date_received_soa' => $this->eventAt($createdAt, 6)],
            'For Creation', 'For Compliance' => [
                ...$endorsed,
                'date_received_soa' => $this->eventAt($createdAt, 6),
                'date_pulled_out' => $this->eventAt($createdAt, 7),
            ],
            'For Review by RMCD', 'On Process', 'For Obligation', 'For Pre-Audit', 'For Crediting', 'Paid' => [
                ...$endorsed,
                ...$this->datesThrough($status, $createdAt),
            ],
            'Cancelled by RMCD' => [
                ...$endorsed,
                ...$this->datesThrough('For Review by RMCD', $createdAt),
                'date_cancelled_by_rmcd' => $this->eventAt($createdAt, 9),
            ],
            default => [],
        };
    }

    /** @return array<string, string> */
    private function datesThrough(string $status, CarbonImmutable $createdAt): array
    {
        $dates = [
            'date_received_soa' => $this->eventAt($createdAt, 6),
            'date_pulled_out' => $this->eventAt($createdAt, 7),
            'date_fwd_to_reviewer' => $this->eventAt($createdAt, 8),
        ];
        if (in_array($status, ['For Obligation', 'For Pre-Audit', 'For Crediting', 'Paid'], true)) {
            $dates['date_fwd_to_budget'] = $this->eventAt($createdAt, 9);
        }
        if (in_array($status, ['For Pre-Audit', 'For Crediting', 'Paid'], true)) {
            $dates['date_fwd_to_actg'] = $this->eventAt($createdAt, 11);
        }
        if ($status === 'Paid') {
            $dates['date_fwd_to_dir_dv'] = $this->eventAt($createdAt, 12);
            $dates['date_fwd_to_cash'] = $this->eventAt($createdAt, 13);
        }

        return $dates;
    }

    private function eventAt(CarbonImmutable $createdAt, int $step): string
    {
        $availableSeconds = $this->latestEventDate->getTimestamp() - $createdAt->getTimestamp();

        return $createdAt->addSeconds(intdiv($availableSeconds * $step, 14))->format('Y-m-d H:i:s');
    }
}
