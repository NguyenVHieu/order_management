<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge(
            parent::toArray($request), // Trả về tất cả các cột của bảng orders
            [
                'shop_name' => $this->shop->name ?? null,
                'seller' => $this->approver->name ?? null,
                'category' => $this->category->name ?? null,
            ]
        );
    }
}
