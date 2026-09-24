<?php

namespace App\Http\Controllers;

use App\Exceptions\Domain\InsufficientStockException;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    /**
     * Thin by design: validate, delegate to the service, translate the
     * result into a redirect. No pricing, stock, or transaction logic here
     * — see AI_AGENT.md §2.1.
     */
    public function store(StoreOrderRequest $request): RedirectResponse
    {
        $data = $request->validated();

        try {
            $order = $this->orderService->placeOrder(
                productId: (int) $data['product_id'],
                quantity: (int) $data['quantity'],
            );
        } catch (InsufficientStockException $exception) {
            throw ValidationException::withMessages([
                'quantity' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('orders.show', $order)
            ->with('status', "Order {$order->order_number} created.");
    }

    public function show(Order $order): View
    {
        return view('orders.show', [
            'order' => $order->load('product'),
        ]);
    }
}
