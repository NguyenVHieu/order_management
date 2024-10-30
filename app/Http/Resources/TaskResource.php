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
            'price' => $this->level_task,
            'task_creator' => $this->createdBy->name ?? '',
            'task_recipient' => $this->designer->name ?? '',
        ];
    }
}
