<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class MockPaymentRequest extends FormRequest
{
    /**
     * Token verification already happened in VerifyMockPaymentToken before
     * this request class runs, so authorization here is just "shape is ok".
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `status` is validated only as "present and a string" here — whether
     * it is the *supported* value ("paid") is a business rule checked in
     * PaymentService, not a validation rule, per the decision flow in
     * AI_AGENT.md §2.4 (unsupported_status is a distinct rejection reason
     * from validation_failed).
     */
    public function rules(): array
    {
        return [
            'event_id' => ['required', 'string', 'max:255'],
            'order_number' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Overridden so validation failures use the same response envelope as
     * every other outcome of this endpoint (AI_AGENT.md §3.3), instead of
     * Laravel's default JSON error shape.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'result' => 'rejected',
            'code' => 'validation_failed',
            'message' => 'The payment payload is invalid.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
