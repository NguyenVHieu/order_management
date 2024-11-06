<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string)$this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->name,
            'createdAt' => $this->created_at,
            'count_product' => $this->count_product,
            'user' => $this->createdBy->name ?? '',
            'design_recipient' => $this->designer->name ?? '',
            'file' => $this->images->pluck('image_url')->all(),
        ];
    }
}
