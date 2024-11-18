<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'user_type_id' => $this->user_type_id,
            'is_admin' => $this->is_admin,
            'team_id' => $this->team_id,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'folder' => $this->folder,
            'avatar_src' => $this->avatar, // Sử dụng accessor avatar_src
            'token' => $this->token ?? null, // Token sẽ được thêm qua `additional`
        ];
    }
}
