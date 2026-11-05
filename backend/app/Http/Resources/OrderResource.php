<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Order\OrderStateMachine;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
final class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            'total_amount' => (string) $this->total_amount,
            'order_date' => $this->order_date->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancel_reason' => $this->cancel_reason,
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'customer_name' => $this->customer->customer_name,
                'email' => $this->customer->email,
            ]),
            'created_by' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'items_count' => $this->whenCounted('items'),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'status_histories' => $this->whenLoaded('statusHistories', fn () => $this->statusHistories->map(
                fn (OrderStatusHistory $h): array => [
                    'from_status' => $h->from_status,
                    'to_status' => $h->to_status,
                    'note' => $h->note,
                    'changed_by' => $h->changedBy?->name,
                    'created_at' => $h->created_at->toIso8601String(),
                ],
            )),
            // Frontend memakai ini untuk menampilkan tombol aksi yang sesuai status & role.
            'available_actions' => $user instanceof User
                ? array_map(
                    fn ($s): string => $s->value,
                    app(OrderStateMachine::class)->availableTransitions($this->status, $user->role),
                )
                : [],
        ];
    }
}
