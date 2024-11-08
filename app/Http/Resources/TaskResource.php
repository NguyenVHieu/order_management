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
            'design_recipient_id' => $this->designer->id,
            'file' => $this->images->pluck('image_url')->all(),
            'is_done' => $this->is_done,
            'done_at' => $this->done_at,
            'template' => $this->template->name ?? '',
            'template_id' => $this->template->id,
            'category' => $this->category->name ?? '',
            'category_design_id' => $this->category->id,
            'url_done' => $this->url_done
        ];
    }
}
