<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Deliberately no rule for `price` or `total`: any such fields sent in
     * the request are simply absent from validated() and are never read
     * anywhere downstream. See AI_AGENT.md §3.2.
     *
     * Stock sufficiency is NOT checked here — only inside OrderService's
     * locked transaction, to avoid validating against a stale read.
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
