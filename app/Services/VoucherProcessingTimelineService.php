<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Compliance;
use App\Models\GuaranteeLetter;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class VoucherProcessingTimelineService
{
    /** @var list<string> */
    private const STEPS = [
        'Awaiting SOA',
        'For Pullout',
        'For Creation',
        'For Review by RMCD',
        'On Process',
        'For Obligation',
        'For Pre-Audit',
        'For Crediting',
        'Paid',
    ];

    /** @var array<string, string> */
    private const EVENT_STATUSES = [
        'record_soa_received' => 'For Pullout',
        'record_pullout' => 'For Creation',
        'create_voucher' => 'For Review by RMCD',
        'forward_to_box_a' => 'On Process',
        'box_a_reviewed' => 'On Process',
        'forward_to_box_b' => 'For Obligation',
        'forward_to_box_c' => 'For Pre-Audit',
        'forward_to_box_d' => 'For Crediting',
        'cip_dir_signed' => 'For Crediting',
        'forward_to_cash' => 'For Crediting',
        'mark_as_paid' => 'Paid',
    ];

    /** @return array{steps: list<array<string, mixed>>, compliance_events: list<array<string, mixed>>} */
    public function forBatch(Batch $batch): array
    {
        return $this->buildTimeline($batch->gls->first()?->status?->name, $this->activitiesForBatch($batch));
    }

    /** @return array{steps: list<array<string, mixed>>, compliance_events: list<array<string, mixed>>} */
    public function forGuaranteeLetter(GuaranteeLetter $letter): array
    {
        return $this->buildTimeline($letter->status?->name, $this->activitiesForGuaranteeLetter($letter));
    }

    /** @param Collection<int, Activity> $activities
     * @return array{steps: list<array<string, mixed>>, compliance_events: list<array<string, mixed>>}
     */
    private function buildTimeline(?string $currentStatus, Collection $activities): array
    {
        $latestReturn = $activities->where('event', 'return_for_compliance')->last();
        $latestResolution = $activities->where('event', 'resolve_compliance')->last();
        $isWaitingForCompliance = $currentStatus === 'For Compliance'
            && $latestReturn !== null
            && ($latestResolution === null || $latestReturn->id > $latestResolution->id);
        $effectiveStatus = $isWaitingForCompliance
            ? $latestReturn->properties->get('from_status')
            : $currentStatus;
        $statusEvents = $activities
            ->filter(fn (Activity $activity): bool => array_key_exists((string) $activity->event, self::EVENT_STATUSES))
            ->groupBy(fn (Activity $activity): string => $this->activityStatus($activity))
            ->map(fn (Collection $events): Activity => $events->last());

        $currentIndex = array_search($effectiveStatus, self::STEPS, true);
        $steps = collect(self::STEPS)->map(function (string $status, int $index) use ($currentIndex, $statusEvents): array {
            $activity = $statusEvents->get($status);

            return [
                'status' => $status,
                'state' => $currentIndex === false ? ($activity ? 'completed' : 'upcoming') : ($index < $currentIndex ? 'completed' : ($index === $currentIndex ? 'current' : 'upcoming')),
                'actor' => $this->actorName($activity),
                'occurred_at' => $activity?->created_at?->toIso8601String(),
                'action' => $activity?->description,
            ];
        })->all();

        $complianceEvents = $activities
            ->filter(fn (Activity $activity): bool => in_array($activity->event, ['return_for_compliance', 'resolve_compliance'], true))
            ->map(function (Activity $activity): array {
                $properties = $activity->properties ?? collect();
                $afterStatus = $this->complianceAfterStatus($activity, $properties);

                return [
                    'type' => $activity->event === 'return_for_compliance' ? 'returned' : 'resolved',
                    'after_status' => in_array($afterStatus, self::STEPS, true) ? $afterStatus : null,
                    'actor' => $this->actorName($activity),
                    'occurred_at' => $activity->created_at?->toIso8601String(),
                    'remarks' => $properties->get('remarks'),
                    'classification' => $this->complianceType($activity, $properties),
                ];
            })
            ->filter(fn (array $event): bool => $event['after_status'] !== null)
            ->values()
            ->all();

        return ['steps' => $steps, 'compliance_events' => $complianceEvents];
    }

    /** @return Collection<int, Activity> */
    private function activitiesForBatch(Batch $batch): Collection
    {
        return Activity::query()
            ->with('causer:id,name')
            ->where(function ($query) use ($batch): void {
                $query->where(function ($subjectQuery) use ($batch): void {
                    $subjectQuery->where('subject_type', Batch::class)->where('subject_id', $batch->getKey());
                })->orWhere(function ($propertiesQuery) use ($batch): void {
                    $propertiesQuery->where('properties->batch_id', $batch->getKey())
                        ->whereIn('event', ['create_voucher', 'resolve_compliance']);
                });
            })
            ->whereIn('event', [...array_keys(self::EVENT_STATUSES), 'return_for_compliance', 'resolve_compliance'])
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int, Activity> */
    private function activitiesForGuaranteeLetter(GuaranteeLetter $letter): Collection
    {
        $batchIds = $letter->batches()->pluck('batches.id');
        $glComplianceIds = Compliance::query()
            ->where('guarantee_letter_id', $letter->getKey())
            ->whereNull('batch_id')
            ->pluck('id');
        $voucherComplianceIds = Compliance::query()
            ->whereIn('batch_id', $batchIds)
            ->pluck('id');
        $complianceIds = $glComplianceIds->merge($voucherComplianceIds)->unique()->values();

        return Activity::query()
            ->with('causer:id,name')
            ->where(function ($query) use ($letter, $complianceIds, $batchIds): void {
                $query->where(function ($subjectQuery) use ($letter): void {
                    $subjectQuery->where('subject_type', GuaranteeLetter::class)->where('subject_id', $letter->getKey());
                })->when($complianceIds->isNotEmpty(), function ($activityQuery) use ($complianceIds): void {
                    $activityQuery->orWhere(function ($complianceQuery) use ($complianceIds): void {
                        $complianceQuery->where('subject_type', Compliance::class)->whereIn('subject_id', $complianceIds);
                    });
                })->when($batchIds->isNotEmpty(), function ($activityQuery) use ($batchIds): void {
                    $activityQuery->orWhere(function ($batchQuery) use ($batchIds): void {
                        $batchQuery->where('subject_type', Batch::class)->whereIn('subject_id', $batchIds);
                    });
                });
            })
            ->whereIn('event', [...array_keys(self::EVENT_STATUSES), 'return_for_compliance', 'resolve_compliance'])
            ->orderBy('id')
            ->get()
            ->filter(function (Activity $activity): bool {
                // Batch actions are logged once on the batch and once on each included GL.
                // The GL view consumes the batch record for shared workflow history, while
                // retaining only GL-owned events (and the voucher-creation handoff) here.
                return $activity->subject_type !== GuaranteeLetter::class
                    || $activity->event === 'create_voucher'
                    || ! $activity->properties?->has('batch_id');
            })
            ->values();
    }

    private function actorName(?Activity $activity): ?string
    {
        return $activity?->causer?->name;
    }

    private function activityStatus(Activity $activity): string
    {
        // The final workflow action belongs to the terminal Paid stage, even
        // though its recorded source status is For Crediting.
        if ($activity->event === 'mark_as_paid') {
            return 'Paid';
        }

        $fromStatus = $activity->properties?->get('from_status');

        return in_array($fromStatus, self::STEPS, true)
            ? $fromStatus
            : self::EVENT_STATUSES[$activity->event];
    }

    private function complianceAfterStatus(Activity $activity, Collection $properties): ?string
    {
        $pausedStatus = $activity->event === 'resolve_compliance'
            ? $properties->get('to_status')
            : $properties->get('from_status');

        $statusIndex = array_search($pausedStatus, self::STEPS, true);

        // A compliance loop is shown before the paused/resumed workflow stage.
        // This keeps every path consistent: prior step → return → resolved → next step.
        return $statusIndex === false || $statusIndex === 0
            ? $pausedStatus
            : self::STEPS[$statusIndex - 1];
    }

    private function complianceType(Activity $activity, Collection $properties): ?string
    {
        if ($properties->get('compliance_type')) {
            return $properties->get('compliance_type');
        }

        if ($activity->event !== 'return_for_compliance') {
            return null;
        }

        return match ($properties->get('from_status')) {
            'For Creation' => 'Voucher Creation to Encoder',
            'For Review by RMCD' => 'Reviewer to Voucher Creation',
            'On Process' => 'Box A Reviewer to Reviewer',
            'For Obligation' => 'Budget to Compliance Officer',
            'For Pre-Audit' => 'Accounting to Compliance Officer',
            'For Crediting' => 'Cash to Reviewer',
            default => null,
        };
    }
}
