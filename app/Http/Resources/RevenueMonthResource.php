<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RevenueMonthResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shop_id' => $this->shop_id,
            'shop_name' => $this->shop->name,
            'revenue' => $this->revenue,
            'year' => $this->year,
            'month' => $this->month,
            'created_by' => $this->createdBy->name ?? '',
            'created_at' => $this->created_at,
            'avatar_src' => $this->createdBy->avatar ?? ''
        ];
    }
}
