<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskRequestResource extends JsonResource
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
            'task_id' => $this->task_id,
            'task_name' => $this->task->title ?? '',
            'request_from' => $this->requestFrom->name ?? '',
            'request_to' => $this->requestTo->name ?? '',
            'approval' => !empty($this->approval) ? (boolean)$this->approval : null,
            'description' => $this->description,
            'score_request' => $this->score_request,
            'score_task' => $this->score_task,
            'score_approval' => $this->score_approval,
            'approved_at' => $this->approved_at,
            'approved_by' => $this->approvedBy->name ?? '',
        ];
    }
}
