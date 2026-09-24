<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MockPaymentRequest;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;

class MockPaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService) {}

    /**
     * Token verification happens in VerifyMockPaymentToken (routed
     * middleware), payload shape in MockPaymentRequest, and every business
     * rule in PaymentService. This controller only assembles the response
     * envelope defined in AI_AGENT.md §3.3.
     */
    public function store(MockPaymentRequest $request): JsonResponse
    {
        $outcome = $this->paymentService->handle($request->validated());

        return response()->json([
            'result' => $outcome->result->value,
            'code' => $outcome->code->value,
            'message' => $outcome->message,
            'order_number' => $outcome->order?->order_number,
            'order_status' => $outcome->order?->status->value,
        ], $outcome->code->httpStatus());
    }
}
