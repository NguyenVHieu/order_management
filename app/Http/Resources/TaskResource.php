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
        $coverImages = $this->taskDoneImages->where('is_cover', 1)->pluck('image_url')->first();
        $imageUrls = $this->images->pluck('image_url')->all() ?? [];
        $imageUrlFirst = $imageUrls[0] ?? '';
        if ($coverImages && $imageUrlFirst !== $coverImages) {
            array_unshift($imageUrls, $coverImages);
        }
        
        return [
            'id' => (string)$this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->name ?? '',
            'createdAt' => $this->created_at,
            'count_product' => $this->count_product,
            'user' => $this->createdBy->name ?? '',
            'user_avatar' => $this->createdBy->avatar ?? '',    
            'design_recipient' => $this->designer->name ?? '',
            'design_recipient_id' => $this->designer->id ?? null,
            'design_recipient_avatar' => $this->designer->avatar ?? '',
            'imageUrl' => $imageUrls,
            'is_done' => $this->is_done,
            'done_at' => $this->done_at,
            'template' => $this->template->name ?? '',
            'template_id' => $this->template->id ?? null,
            'product_id' => $this->template->product->id ?? null,
            'product' => $this->template->product->name ?? '',
            'platform_size' => $this->template->platformSize->name ?? '',    
            'platform_size_id' => $this->template->platformSize->id ?? null,
            'category' => $this->category->name ?? '',
            'category_design_id' => $this->category->id ?? null,
            'image_src_review' => $this->taskDoneImages->pluck('image_url')->all() ?? []
        ];
    }
}
