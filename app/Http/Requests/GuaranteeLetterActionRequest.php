<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuaranteeLetterActionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user('api');
        $action = $this->input('action');

        if ($action === 'update_obligation_amount') {
            return $user?->hasAnyRole(['admin', 'encoder', 'reviewer']) ?? false;
        }

        if (in_array($this->input('action'), ['approve_gl', 'release_gl'], true)) {
            return $user?->hasRole('admin') ?? false;
        }

        return $user?->hasAnyRole(['admin', 'encoder'])
            || ($action === 'return_for_compliance' && $user?->hasRole('voucher creation'))
            || false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['approve_gl', 'release_gl', 'record_soa_received', 'record_pullout', 'return_for_compliance', 'update_obligation_amount'])],
            'date_received_soa' => ['required_if:action,record_soa_received', 'prohibited_unless:action,record_soa_received', 'date_format:Y-m-d'],
            'obligation_amount' => ['required_if:action,update_obligation_amount', 'prohibited_unless:action,record_soa_received,update_obligation_amount', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'date_pulled_out' => ['required_if:action,record_pullout', 'prohibited_unless:action,record_pullout', 'date_format:Y-m-d'],
            'forwarded_date' => ['required_if:action,return_for_compliance', 'prohibited_unless:action,return_for_compliance', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Manila')->toDateString()],
            'remarks' => ['required_if:action,return_for_compliance', 'nullable', 'string', 'max:2000'],
            'supporting_documents' => ['sometimes', 'prohibited_unless:action,record_soa_received', 'array', 'max:'.config('supporting_documents.max_files')],
            'supporting_documents.*' => ['required', 'file', 'image', 'mimetypes:'.implode(',', config('supporting_documents.mime_types')), 'max:'.config('supporting_documents.max_size_kb'), 'dimensions:max_width='.config('supporting_documents.max_dimension').',max_height='.config('supporting_documents.max_dimension')],
            'status_id' => ['prohibited'],
            'soa_received_by' => ['prohibited'],
            'pulled_out_by' => ['prohibited'],
        ];
    }
}
