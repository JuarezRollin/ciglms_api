<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Compliance;
use App\Models\GuaranteeLetter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class AuditTrailService
{
    /** @var array<string, array{title: string, icon: string, tone: string}> */
    private const EVENT_PRESENTATION = [
        'record_soa_received' => ['title' => 'SOA receipt recorded', 'icon' => 'pi-calendar-plus', 'tone' => 'blue'],
        'record_pullout' => ['title' => 'GL pullout recorded', 'icon' => 'pi-file-edit', 'tone' => 'blue'],
        'create_voucher' => ['title' => 'Voucher created', 'icon' => 'pi-file', 'tone' => 'blue'],
        'record_financial_details' => ['title' => 'Financial details recorded', 'icon' => 'pi-wallet', 'tone' => 'blue'],
        'forward_to_box_a' => ['title' => 'Forwarded to Box A', 'icon' => 'pi-send', 'tone' => 'blue'],
        'box_a_reviewed' => ['title' => 'Box A review completed', 'icon' => 'pi-check-circle', 'tone' => 'green'],
        'forward_to_box_b' => ['title' => 'Forwarded to Box B', 'icon' => 'pi-send', 'tone' => 'blue'],
        'forward_to_box_c' => ['title' => 'Forwarded to Box C', 'icon' => 'pi-send', 'tone' => 'blue'],
        'forward_to_box_d' => ['title' => 'Forwarded to Director Box D', 'icon' => 'pi-send', 'tone' => 'blue'],
        'cip_dir_signed' => ['title' => 'Signed by CIP Director', 'icon' => 'pi-check-square', 'tone' => 'green'],
        'forward_to_cash' => ['title' => 'Forwarded to Cash', 'icon' => 'pi-send', 'tone' => 'blue'],
        'mark_as_paid' => ['title' => 'Marked as paid', 'icon' => 'pi-money-bill', 'tone' => 'green'],
        'return_for_compliance' => ['title' => 'Returned for compliance', 'icon' => 'pi-replay', 'tone' => 'red'],
        'resolve_compliance' => ['title' => 'Compliance resolved', 'icon' => 'pi-check', 'tone' => 'green'],
        'update_obligation_amount' => ['title' => 'Obligation amount updated', 'icon' => 'pi-wallet', 'tone' => 'blue'],
    ];

    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    public function forGuaranteeLetter(GuaranteeLetter $letter, int $perPage): LengthAwarePaginator
    {
        $complianceIds = Compliance::query()->where('guarantee_letter_id', $letter->getKey())->pluck('id');

        return $this->paginate(
            Activity::query()
                ->with('causer:id,name')
                ->where(function ($query) use ($letter, $complianceIds): void {
                    $query->where(function ($subjectQuery) use ($letter): void {
                        $subjectQuery->where('subject_type', GuaranteeLetter::class)->where('subject_id', $letter->getKey());
                    })->when($complianceIds->isNotEmpty(), function ($activityQuery) use ($complianceIds): void {
                        $activityQuery->orWhere(function ($complianceQuery) use ($complianceIds): void {
                            $complianceQuery->where('subject_type', Compliance::class)->whereIn('subject_id', $complianceIds);
                        });
                    });
                }),
            $perPage,
        );
    }

    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    public function forBatch(Batch $batch, int $perPage): LengthAwarePaginator
    {
        $complianceIds = Compliance::query()->where('batch_id', $batch->getKey())->pluck('id');

        return $this->paginate(
            Activity::query()
                ->with('causer:id,name')
                ->where(function ($query) use ($batch, $complianceIds): void {
                    $query->where(function ($subjectQuery) use ($batch): void {
                        $subjectQuery->where('subject_type', Batch::class)->where('subject_id', $batch->getKey());
                    })->when($complianceIds->isNotEmpty(), function ($activityQuery) use ($complianceIds): void {
                        $activityQuery->orWhere(function ($complianceQuery) use ($complianceIds): void {
                            $complianceQuery->where('subject_type', Compliance::class)->whereIn('subject_id', $complianceIds);
                        });
                    });
                }),
            $perPage,
        );
    }

    /** @return array{data: array<int, array<string, mixed>>, meta: array<string, int>} */
    public function payload(LengthAwarePaginator $activities): array
    {
        return [
            'data' => $activities->items(),
            'meta' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
            ],
        ];
    }

    /**
     * @param  Builder<Activity>  $query
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function paginate($query, int $perPage): LengthAwarePaginator
    {
        $activities = $query->latest('id')->paginate($perPage);
        $activities->setCollection($activities->getCollection()->map(fn (Activity $activity): array => $this->present($activity)));

        return $activities;
    }

    /** @return array<string, mixed> */
    private function present(Activity $activity): array
    {
        $properties = $activity->properties ?? collect();
        $oldAttributes = $properties->get('old_attributes', []);
        $newAttributes = $properties->get('new_attributes', []);
        $presentation = self::EVENT_PRESENTATION[$activity->event] ?? [
            'title' => $activity->description ?: 'Record updated',
            'icon' => 'pi-history',
            'tone' => 'slate',
        ];

        return [
            'id' => $activity->id,
            'event' => $activity->event,
            'title' => $presentation['title'],
            'description' => $activity->description,
            'icon' => $presentation['icon'],
            'tone' => $presentation['tone'],
            'from_status' => $properties->get('from_status'),
            'to_status' => $properties->get('to_status'),
            'remarks' => $properties->get('remarks'),
            'supporting_documents_count' => $properties->get('supporting_documents_count'),
            'obligation_amount_change' => $activity->event === 'update_obligation_amount' && array_key_exists('obligation_amount', $newAttributes)
                ? [
                    'from' => $oldAttributes['obligation_amount'] ?? null,
                    'to' => $newAttributes['obligation_amount'],
                ]
                : null,
            'actor' => $activity->causer?->name,
            'occurred_at' => $activity->created_at?->toIso8601String(),
        ];
    }
}
