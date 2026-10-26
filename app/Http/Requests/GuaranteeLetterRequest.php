<?php

namespace App\Http\Requests;

use App\Models\GuaranteeLetter;
use App\Models\ServiceProvider;
use App\Models\Status;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class GuaranteeLetterRequest extends FormRequest
{
    private const STATUS_ORDER = [
        'Awaiting SOA',
        'For Pullout',
        'For Creation',
        'For Compliance',
        'On Process',
        'For Obligation',
        'For Pre-Audit',
        'For Crediting',
        'Paid',
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'voucher_no' => ['prohibited'],
            'voucher_id' => ['prohibited'],
            'ors_no' => ['prohibited'],
            'ors_id' => ['prohibited'],
            'dv_no' => ['prohibited'],
            'dv_id' => ['prohibited'],
            'cheque_no' => ['prohibited'],
            'cheque_id' => ['prohibited'],
            'cheque_date' => ['prohibited'],
            'compliance_received_date' => ['prohibited'],
            'compliance_forwarded_date' => ['prohibited'],
            'compliance_remarks' => ['prohibited'],
            'date_created' => ['prohibited'],
            'date_created_reviewed' => ['prohibited'],
            'date_recieving_endorsement' => ['prohibited'],
            'encoded_by' => ['prohibited'],
            'reviewed_by' => ['prohibited'],
            'date_cancelled' => ['prohibited'],
            'date_cancelled_by_processing' => ['prohibited'],
            'gl_creation_remarks' => ['prohibited'],
            'remarks' => ['prohibited'],
            'date_fwd_to_signatory' => ['prohibited'],
            'date_returned_by_signatory' => ['prohibited'],
            // 'office_id' => ['required', 'integer', 'exists:offices,id'],
            // 'gl_no' => ['required', 'string', 'max:100', Rule::unique('guarantee_letters')->ignore($this->route('guarantee_letter'))],
            'gl_date' => ['required', 'date'],
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'beneficiary_id' => ['required', 'integer', 'exists:beneficiaries,id'],
            'address' => ['required', 'string', 'max:255'],
            'relationship_id' => ['nullable', 'integer', 'exists:relationships,id'],
            'service_provider_id' => ['required', 'integer', 'exists:service_providers,id'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('service_provider_id', $this->input('service_provider_id'))],
            'fund_source_id' => [Rule::requiredIf(fn (): bool => $this->statusAtOrAfter('On Process')), 'nullable', 'integer', 'exists:fund_sources,id'],
            'assistance_type_id' => ['required', 'integer', 'exists:assistance_types,id'],
            'status_id' => $this->isMethod('post') ? ['prohibited'] : ['required', 'integer', 'exists:statuses,id'],
            'gl_amount' => ['required', 'numeric', 'min:0'],
            'obligation_amount' => [Rule::requiredIf(fn (): bool => $this->statusAtOrAfter('On Process')), 'nullable', 'numeric', 'min:0'],
            'signatory_id' => [
                'required',
                'integer',
                Rule::exists('signatories', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('minimum_amount', '<=', $this->input('gl_amount'))
                        ->where('maximum_amount', '>=', $this->input('gl_amount'))
                        ->where('is_active', true),
                ),
            ],
            'gl_validity' => ['required', 'date'],
            'swo_id' => ['required', 'integer', 'exists:swos,id'],
            'referring_party_id' => ['nullable', 'integer', 'exists:referring_parties,id'],
            'creation_remarks' => ['nullable', 'string'],
            'reviewed_by_creation' => ['nullable', 'integer', 'exists:users,id'],
            'date_reviewed_by_creation' => ['nullable', 'date'],
            'approved_by' => ['nullable', 'integer', 'exists:users,id'],
            'date_approved' => ['nullable', 'date'],
            'released_by' => ['nullable', 'integer', 'exists:users,id'],
            'date_released' => ['nullable', 'date'],
            'endorsed_by' => ['nullable', 'integer', 'exists:users,id'],
            'date_endorsed' => ['nullable', 'date'],
            'onhold_by' => ['nullable', 'integer', 'exists:users,id'],
            'date_onhold' => ['nullable', 'date'],
            'cancelled_by_creation' => ['nullable', 'integer', 'exists:users,id'],
            'date_cancelled_by_creation' => ['nullable', 'date'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'received_by' => [Rule::prohibitedIf(fn (): bool => $this->hasActiveTransmittal()), 'nullable', 'integer', 'exists:users,id'],
            'soa_received_by' => ['nullable', 'integer', 'exists:users,id'],
            'pulled_out_by' => ['nullable', 'integer', 'exists:users,id'],
            'reviewed_by_rmcd' => ['nullable', 'integer', 'exists:users,id'],
            'cancelled_by_rmcd' => ['nullable', 'integer', 'exists:users,id'],
            'date_cancelled_by_rmcd' => ['nullable', 'date'],
            'norsa_no' => ['nullable', 'string', 'max:100'],
            'processing_remarks' => ['nullable', 'string'],
            'date_received_gl' => $this->isMethod('post') || $this->hasActiveTransmittal() ? ['prohibited'] : ['nullable', 'date'],
            'date_received_soa' => [Rule::requiredIf(fn (): bool => $this->statusAtOrAfter('For Pullout')), 'nullable', 'date'],
            'date_pulled_out' => [Rule::requiredIf(fn (): bool => $this->statusAtOrAfter('For Creation')), 'nullable', 'date'],
            'date_fwd_to_reviewer' => ['nullable', 'date'],
            'date_fwd_to_dc' => ['nullable', 'date'],
            'date_fwd_to_dir_ors' => ['nullable', 'date'],
            'date_fwd_to_budget' => [Rule::requiredIf(fn (): bool => $this->statusAtOrAfter('For Obligation')), 'nullable', 'date'],
            'date_fwd_to_actg' => [Rule::requiredIf(fn (): bool => $this->statusAtOrAfter('For Pre-Audit')), 'nullable', 'date'],
            'date_fwd_to_dir_dv' => [Rule::requiredIf(fn (): bool => $this->statusAtOrAfter('Paid')), 'nullable', 'date'],
            'date_fwd_to_dir_fs' => ['nullable', 'date'],
            'date_fwd_to_cash' => [Rule::requiredIf(fn (): bool => $this->statusAtOrAfter('Paid')), 'nullable', 'date'],
            // 'created_by' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->isMethod('post') && ! $validator->errors()->has('service_provider_id')) {
                $provider = ServiceProvider::query()->find($this->input('service_provider_id'));
                if ($provider && strcasecmp(trim($provider->status ?? ''), 'Suspended') === 0) {
                    $validator->errors()->add('service_provider_id', 'Guarantee letters cannot be created for a suspended service provider.');
                }
            }

            if ($validator->errors()->has('status_id')) {
                return;
            }

            $letter = $this->route('guarantee_letter');
            if (! $this->isMethod('post') && $letter instanceof GuaranteeLetter
                && (int) $this->input('status_id') !== (int) $letter->status_id) {
                $currentStatus = (int) $letter->status_id;
                $nextStatus = (int) $this->input('status_id');
                $creationStatuses = [Status::FOR_REVIEW_BY_CRD_ID, Status::APPROVED_ID, Status::RELEASED_ID];
                if ($currentStatus === Status::FOR_ENDORSEMENT_ID) {
                    $validator->errors()->add('status_id', 'Use the transmittal to receive or cancel this GL.');

                    return;
                }
                if (in_array($nextStatus, [...$creationStatuses, Status::FOR_ENDORSEMENT_ID], true)
                    || (in_array($currentStatus, $creationStatuses, true)
                        && ! in_array($nextStatus, [Status::ON_HOLD_ID, Status::CANCELLED_BY_CRD_ID], true))) {
                    $validator->errors()->add('status_id', 'Use the GL creation actions or transmittal to change this status.');

                    return;
                }
            }

            $requiredReferences = match (true) {
                $this->statusAtOrAfter('Paid') => ['voucher_id', 'ors_id', 'dv_id', 'cheque_id'],
                $this->statusAtOrAfter('For Pre-Audit') => ['voucher_id', 'ors_id', 'dv_id'],
                $this->statusAtOrAfter('On Process') => ['voucher_id'],
                default => [],
            };

            if ($requiredReferences === []) {
                return;
            }

            $guaranteeLetter = $this->route('guarantee_letter');
            if (! $guaranteeLetter instanceof GuaranteeLetter) {
                $validator->errors()->add('batches', 'Attach the Guarantee Letter to its accounting batch before selecting this status.');

                return;
            }

            $hasCompleteBatch = $guaranteeLetter->batches()
                ->where(function (\Illuminate\Database\Eloquent\Builder $query) use ($requiredReferences): void {
                    foreach ($requiredReferences as $reference) {
                        $query->whereNotNull('batches.'.$reference);
                    }
                })
                ->exists();

            if (! $hasCompleteBatch) {
                $validator->errors()->add(
                    'batches',
                    'The selected status requires one batch with '.implode(', ', $requiredReferences).'.',
                );
            }
        }];
    }

    private function hasActiveTransmittal(): bool
    {
        $letter = $this->route('guarantee_letter');

        return $letter instanceof GuaranteeLetter
            && $letter->transmittals()->whereNull('gl_transmittals.cancelled_at')->exists();
    }

    private function statusAtOrAfter(string $milestone): bool
    {
        $status = Status::query()->find($this->input('status_id'))?->name;
        $statusPosition = array_search($status, self::STATUS_ORDER, true);
        $milestonePosition = array_search($milestone, self::STATUS_ORDER, true);

        return $statusPosition !== false && $milestonePosition !== false && $statusPosition >= $milestonePosition;
    }
}
